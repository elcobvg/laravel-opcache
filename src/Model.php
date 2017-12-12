<?php

namespace ElcoBvg\Opcache;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Model extends Eloquent
{
    /**
     * Magic method to restore OPcache objects from cache
     *
     * @param  array $array
     */
    public static function __set_state(array $array)
    {
        $class = get_called_class();
        $object = new $class;
        foreach ($array['attributes'] as $key => $value) {
            $object->{$key} = $value;
        }
        return $object;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \ElcoBvg\Opcache\Builder
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
