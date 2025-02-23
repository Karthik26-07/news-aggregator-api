<?php

namespace App\Traits;

use App\Classes\Common;

trait Hashable
{
    public function __call($method, $arguments)
    {
        if (isset($this->hashableGetterFunctions) && isset($this->hashableGetterFunctions[$method])) {
            $value = $this->{$this->hashableGetterFunctions[$method]};
            return $value ? Common::hashId($value) : $value;
        }

        if (isset($this->hashableGetterArrayFunctions) && isset($this->hashableGetterArrayFunctions[$method])) {
            $value = $this->{$this->hashableGetterArrayFunctions[$method]};
            return count($value) > 0 ? array_map(fn($id) => Common::hashId($value), $value) : $value;
        }

        return parent::__call($method, $arguments);
    }

    public function getXIDAttribute()
    {
        return Common::hashId($this->id);
    }
}