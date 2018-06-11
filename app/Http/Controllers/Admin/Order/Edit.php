<?php

namespace App\Http\Controllers\Admin\Order;

use App\Define\RetCode;
use App\Http\Controllers\Controller;
use App\Logic\Admin\Order\AdminOrderLogic;

class Edit extends Controller
{
    /**
     * @api {post} /api/admin/v1/order/edit 编辑
     * @apiGroup 分组名称
     *
     * @apiParam {String} data 数据
     *
     *
     * @apiParamExample {json} 请求示例:
     * {"id":"C80BFCD00C2BE9F1","title":"黄鹤楼送孟浩然之广陵","author":"李白","content":"故人西辞黄鹤楼，烟花三月下扬州。孤帆远影碧空尽，唯见长江天际流。"}
     *
     * @apiSuccessExample Success-Response:
     * {"code": 0,"msg": "SUCCESS","data": {}}
     */
    public function run()
    {
        $inputData = $this->only(['id', 'title', 'author', 'content']);

        $logic = new AdminOrderLogic($this->getAdminUser());
        $logic->edit($inputData);

        return $this->render(RetCode::SUCCESS, 'success');
    }

    public function rules()
    {
        return [
            'id'      => ['required|min:3|max:32', 'id'],
            'title'   => ['required', '标题'],
            'author'  => ['required', '作者'],
            'content' => ['required', '内容']
        ];
    }
}