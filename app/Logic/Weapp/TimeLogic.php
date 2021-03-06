<?php

namespace App\Logic\Weapp;

use App\Define\Common;
use App\Define\RetCode;
use App\Logic\BaseLogic;
use App\Models\SentenceModel;
use App\Models\TimeModel;
use App\Tools\ArrayTool;
use App\Tools\StringTool;
use App\Tools\Util;

class TimeLogic extends BaseLogic
{
    public function create(array $inputData)
    {
        $user = $this->user;
        //参数校验
        if (!$this->_validateEditParam($inputData)) {
            Util::errorCode(RetCode::PARAM_ERROR);
        }

        $timeModel = new TimeModel(StringTool::createUuid());

        $inputData['type'] = self::getTypeByDate($inputData['date']);
        $inputData['userId'] = $user->id;

        \DB::beginTransaction();
        $timeModel->add($inputData, $user);
        \DB::commit();
    }

    public function edit(array $inputData)
    {
        $user = $this->user;
        //参数校验
        if (!$this->_validateEditParam($inputData)) {
            Util::errorCode(RetCode::PARAM_ERROR);
        }

        $id = $inputData['id'];
        $timeModel = new TimeModel($id);
        if (!$timeModel->exists) {
            Util::errorCode(RetCode::ERR_OBJECT_NOT_FOUND);
        }

        \DB::beginTransaction();
        $timeModel->edit($inputData, $user);
        \DB::commit();

        return $timeModel;
    }

    private function _validateEditParam(array $inputData)
    {
        //do some validate logic and return true or false
        return true;
    }

    public function getList(int $currentPage = 1, int $perPage = 10, array $condition = [], $format = null): array
    {
        $userId = $this->user->id;
        $condition['userId'] = $userId;

        $model = new TimeModel();
        $models = $model->getList($condition, $currentPage, $perPage);
        $models->map(function ($row, $key) {
            $row->serial = $key;
            return $row;
        });

        //随机获取美句
        $sentenceModel = new SentenceModel();
        $sentenceModelArray = $sentenceModel->getRand($perPage)->toArray();

        return ArrayTool::modelListToArray($models, $format, $sentenceModelArray);
    }

    public function getDetail(string $id, $format = null): array
    {
        $orderModel = new TimeModel($id);
        if (!$orderModel->exists) {
            Util::errorCode(RetCode::ERR_OBJECT_NOT_FOUND);
        }

        return ArrayTool::modelToArray($orderModel, $format);
    }

    public function delete(string $id)
    {
        $orderModel = new TimeModel($id);
        if (!$orderModel->exists) {
            Util::errorCode(RetCode::ERR_OBJECT_NOT_FOUND);
        }

        \DB::beginTransaction();
        $orderModel->delete();
        \DB::commit();
    }

    private static function getTypeByDate($targetData)
    {
        $nowDate = date('Ymd', time());

        if ($targetData > $nowDate) {
            //目标日
            return Common::TIME_TYPE_DESC;
        }

        return Common::TIME_TYPE_ASC;
    }
}