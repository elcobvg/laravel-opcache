<?php

namespace ElcoBvg\Opcache;

use Illuminate\Pagination\LengthAwarePaginator as BasePaginator;

class LengthAwarePaginator extends BasePaginator
{
    /**
     * Magic method to restore OPcache objects from cache
     *
     * @param  array $array
     */
    public static function __set_state(array $array)
    {
        $class = get_called_class();
        return new $class(
            $array['items'],
            $array['total'],
            $array['perPage'],
            $array['lastPage']
        );
    }
}
