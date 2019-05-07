<?php

namespace Clickspace\AdvancedRequest;


class BaseModel extends \Illuminate\Database\Eloquent\Model
{
    use MapRequestFields;

    public $incrementing = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public static $allowableRelationships = [];
    public static $allowableFilters = [];
    public static $defaultIncludes = [];
    public static $uuidAttributes = [];

    public static function filterByAccess($query, $args = [])
    {
    }
}