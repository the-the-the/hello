<?php

namespace App\Http\Middleware;

use App\Exceptions\ThrottleException;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\InteractsWithTime;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRequests
{
    use InteractsWithTime;
    /**
     * The rate limiter instance.
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * Create a new request throttler.
     *
     * @param  \Illuminate\Cache\RateLimiter $limiter
     * @return void
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param         $request
     * @param Closure $next
     * @param int     $maxAttempts
     * @param int     $decayMinutes
     * @return Response
     * @throws ThrottleException
     */
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = $this->resolveMaxAttempts($request, $maxAttempts);
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $this->buildException($key, $maxAttempts);
        }
        $this->limiter->hit($key, $decayMinutes);
        $response = $next($request);
        return $this->addHeaders(
            $response, $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Resolve the number of attempts if the user is authenticated or not.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int|string               $maxAttempts
     * @return int
     */
    protected function resolveMaxAttempts($request, $maxAttempts)
    {
        if (Str::contains($maxAttempts, '|')) {
            $maxAttempts = explode('|', $maxAttempts, 2)[$request->user() ? 1 : 0];
        }
        if (!is_numeric($maxAttempts) && $request->user()) {
            $maxAttempts = $request->user()->{$maxAttempts};
        }
        return (int)$maxAttempts;
    }

    /**
     * Resolve request signature.
     *
     * @param  \Illuminate\Http\Request $request
     * @return string
     * @throws \RuntimeException
     */
    protected function resolveRequestSignature($request)
    {
        return sha1(
            $request->method() .
            '|' . $request->server('SERVER_NAME') .
            '|' . $request->path() .
            '|' . $request->ip()
        );
    }

    /**
     * Create a 'too many attempts' exception.
     *
     * @param $key
     * @param $maxAttempts
     * @throws ThrottleException
     */
    protected function buildException($key, $maxAttempts)
    {
        $retryAfter = $this->getTimeUntilNextRetry($key);
        $headers = $this->getHeaders(
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );

        throw new ThrottleException('Too Many Attempts.', 429);
    }

    /**
     * Get the number of seconds until the next retry.
     *
     * @param  string $key
     * @return int
     */
    protected function getTimeUntilNextRetry($key)
    {
        return $this->limiter->availableIn($key);
    }

    /**
     * Add the limit header information to the given response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response $response
     * @param  int                                        $maxAttempts
     * @param  int                                        $remainingAttempts
     * @param  int|null                                   $retryAfter
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $response->headers->add(
            $this->getHeaders($maxAttempts, $remainingAttempts, $retryAfter)
        );
        return $response;
    }

    /**
     * Get the limit headers information.
     *
     * @param  int      $maxAttempts
     * @param  int      $remainingAttempts
     * @param  int|null $retryAfter
     * @return array
     */
    protected function getHeaders($maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $headers = [
            'X-RateLimit-Limit'     => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];
        if (!is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = $this->availableAt($retryAfter);
        }
        return $headers;
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param  string   $key
     * @param  int      $maxAttempts
     * @param  int|null $retryAfter
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null)
    {
        if (is_null($retryAfter)) {
            return $this->limiter->retriesLeft($key, $maxAttempts);
        }
        return 0;
    }
}
