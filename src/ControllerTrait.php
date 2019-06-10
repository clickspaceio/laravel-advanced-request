<?php

namespace Clickspace\AdvancedRequest;

trait ControllerTrait
{
    protected $model = null;
    protected $resource = DefaultResource::class;
    protected $countResource = null;

    protected $defaultFilter = null;
    protected $defaultFilters = [];
    protected $defaultRelationships = [];

    protected $allowableFilters = [];
    protected $allowableRelationships = [];

    protected $queryFilters = [];

    protected $defaults = [
        'limit' => 50,
        'page' => 1,
    ];

    protected $maxValues = [
        'limit' => 50
    ];

}