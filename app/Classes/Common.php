<?php

namespace App\Classes;

use Vinkla\Hashids\Facades\Hashids;

class Common
{

    public static function hashId($id)
    {
        if ($id === null || !is_numeric($id)) {
            return null;
        }
        return Hashids::encode((int) $id);
    }

    public static function getIdFromHash($hash)
    {
        if ($hash != "") {
            $convertedId = Hashids::decode($hash);
            if (!empty($convertedId) && isset($convertedId[0])) {
                return $convertedId[0];
            }
        }
        return null;
    }
}

