<?php

namespace Clickspace\AdvancedRequest;

use Carbon\Carbon;
use DB;
use function foo\func;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait EloquentBuilder
{

    /**
     * Page sort
     * @param array $sort
     * @return array
     */
    protected function parseSort(array $sort)
    {
        return array_map(function ($sort) {
            if (!isset($sort['direction'])) {
                $sort['direction'] = 'asc';
            }
            return $sort;
        }, $sort);
    }

    /**
     * Parse include strings into resource and modes
     * @param  array $includes
     * @return array The parsed resources and their respective modes
     */
    protected function parseIncludes(array $includes)
    {
        $return = [
            'includes' => [],
            'modes' => []
        ];
        foreach ($includes as $include) {
            $explode = explode(':', $include);
            if (!isset($explode[1])) {
                $explode[1] = camel_case($this->defaults['mode']);
            }
            $return['includes'][] = $explode[0];
            $return['modes'][$explode[0]] = $explode[1];
        }

        return $return;
    }

    /**
     * Parse GET parameters into resource options
     * @return array
     */
    protected function parseResourceOptions($request)
    {
        $this->defaults = array_merge([
            'limit' => null,
            'page' => null,
            'filters' => [],
            'includes' => []
        ], $this->defaults);

        $limit = $request->get('limit', $this->defaults['limit']);
        $page = $request->get('page', $this->defaults['page']);
        $filters = $request->only(array_keys($this->allowableFilters)) ?? $this->defaults['filters'];
        $includes = array_filter($request->get('includes', $this->defaults['includes']), function ($include) { return in_array($include, array_keys($this->allowableRelationships)); });

        if ($page !== null && $limit === null) {
            throw new InvalidArgumentException('Cannot use page option without limit option');
        }

        if ($limit > $this->maxValues['limit'])
            $limit = $this->maxValues['limit'];

        return [
            'limit' => (integer)$limit,
            'page' => (integer)$page,
            'filters' => $filters,
            'includes' => $includes
        ];
    }

    /**
     * Apply resource options to a query builder
     * @param  $queryBuilder
     * @param  array $options
     * @return Builder
     */
    protected function applyResourceOptions($queryBuilder, array $options = [])
    {
        if (empty($options)) {
            return $queryBuilder;
        }

        $request = app('request');

        if (array_key_exists('distinct', $options)) {
            $queryBuilder->distinct();
        }

        //foreach ($this->defaultFilters as $defaultFilter){
        $defaultFilter = $this->defaultFilter;
        $queryBuilder->where($defaultFilter['key'], $request[$defaultFilter['relationship']]->sid);

        foreach ($options['filters'] as $key => $value){


            if (gettype($queryBuilder->getModel()->{$key}) != "NULL" and is_array($value) == true) {
                $this->applyRelationshipFilter($queryBuilder, $key, $value);
            } else {
                $this->applyFilter($queryBuilder, $key, $value);
            }
        }

        $relationships = [];
        foreach ($this->defaultRelationships as $relationship => $type){
            $relationships[] = $relationship;
        }
        foreach ($options['includes'] as $relationship){
            $relationships[] = $relationship;
        }
        $queryBuilder->with($relationships);

        return $queryBuilder;
    }

    protected function applyRelationshipFilter($query, $key, $request) {
        $query->whereHas($key, function ($query) use ($request, $key) {
            foreach ($request as $keyChild => $value) {
                $this->applyFilter($query, $key.".".$keyChild, $value);
            }
        });
    }

    protected function applyFilter($query, $key, $value) {


        if (!isset($this->allowableFilters[$key]))
            return false;

        $operator = '=';
        $method = 'where';


        if (in_array(substr($value,0,2), ['>=', '<='])) {
            $operator = substr($value,0,2);
            $value = str_replace($operator, '', $value);
        } elseif (in_array(substr($value,0,1), ['>', '<'])) {
            $operator = substr($value, 0, 1);
            $value = str_replace($operator, '', $value);
        } elseif (
            $this->allowableFilters[$key] != 'text' and substr($value,0,1) == '[' and substr($value,-1,1) == ']') {
            $method = 'whereBetween';
            $value = explode(' and ', substr($value, 1, strlen($value)-2));
        } elseif ($this->allowableFilters[$key] == 'enum') {
            $method = 'whereIn';
            $value = explode(',',$value);
        } elseif ($value === 'null') {
            $method = 'whereNull';
            $operator = null;
            $value = null;
        }

        if ($operator == '=' and $this->allowableFilters[$key] == 'text'){
            $operator = 'like';
            $value = "%{$value}%";
        }

        switch ($method) {
            case 'whereBetween':
                if ($this->allowableFilters[$key] == 'date') {
                    if (trim(strlen($value[0])) == 10) {
                        $value[0] .= " 00:00:00";
                    }
                    if (trim(strlen($value[1]) == 10)) {
                        $value[1] .= " 23:59:59";
                    }
                }
                $query->whereBetween($key, $value);
                break;
            case 'whereNull':
                $query->whereNull($key);
                break;
            case 'whereIn':
                $query->whereIn($key, $value);
                break;
            default:
                $query->where($key, $operator, $value);
        }

        return $query;
    }

    /**
     * @param $queryBuilder
     * @param array $filterGroups
     * @param array $previouslyJoined
     * @return array
     */
    protected function applyIncludes($queryBuilder, array $includes = [])
    {
        if (!is_array($includes)) {
            throw new InvalidArgumentException('Includes should be an array.');
        }

        foreach ($includes as $include)
            if (!in_array($include, $this->allowableRelationships))
                throw new InvalidArgumentException('Includes: \'' . $include . '\' can not be included.');

        $queryBuilder->with($includes);

        return $queryBuilder;
    }

    /**
     * @param $queryBuilder
     * @param array $filterGroups
     * @param array $previouslyJoined
     * @return array
     */

    /**
     * @param $queryBuilder
     * @param array $sorting
     * @param array $previouslyJoined
     * @return array
     */
    protected function applySorting($queryBuilder, array $sorting, array $previouslyJoined = [])
    {
        $joins = [];
        foreach($sorting as $sortRule) {
            if (is_array($sortRule)) {
                $key = $sortRule['key'];
                $direction = mb_strtolower($sortRule['direction']) === 'asc' ? 'ASC' : 'DESC';
            } else {
                $key = $sortRule;
                $direction = 'ASC';
            }

            $customSortMethod = $this->hasCustomMethod('sort', $key);
            if ($customSortMethod) {
                $joins[] = $key;

                call_user_func([$this, $customSortMethod], $queryBuilder, $direction);
            } else {
                $queryBuilder->orderBy($key, $direction);
            }
        }

        foreach(array_diff($joins, $previouslyJoined) as $join) {
            $this->joinRelatedModelIfExists($queryBuilder, $join);
        }

        return $joins;
    }

    /**
     * @param $type
     * @param $key
     * @return bool|string
     */
    private function hasCustomMethod($type, $key)
    {
        $methodName = sprintf('%s%s', $type, Str::studly($key));
        if (method_exists($this, $methodName)) {
            return $methodName;
        }

        return false;
    }

    /**
     * @param $queryBuilder
     * @param $key
     */
    private function joinRelatedModelIfExists($queryBuilder, $key)
    {
        $model = $queryBuilder->getModel();

        // relationship exists, join to make special sort
        if (method_exists($model, $key)) {
            $relation = $model->$key();
            $type = 'inner';

            if ($relation instanceof BelongsTo) {
                $queryBuilder->join(
                    $relation->getRelated()->getTable(),
                    $model->getTable().'.'.$relation->getQualifiedForeignKeyName(),
                    '=',
                    $relation->getRelated()->getTable().'.'.$relation->getOwnerKey(),
                    $type
                );
            } elseif ($relation instanceof BelongsToMany) {
                $queryBuilder->join(
                    $relation->getTable(),
                    $relation->getQualifiedParentKeyName(),
                    '=',
                    $relation->getQualifiedForeignKeyName(),
                    $type
                );
                $queryBuilder->join(
                    $relation->getRelated()->getTable(),
                    $relation->getRelated()->getTable().'.'.$relation->getRelated()->getKeyName(),
                    '=',
                    $relation->getQualifiedRelatedKeyName(),
                    $type
                );
            } else {
                $queryBuilder->join(
                    $relation->getRelated()->getTable(),
                    $relation->getQualifiedParentKeyName(),
                    '=',
                    $relation->getQualifiedForeignKeyName(),
                    $type
                );
            }

            $table = $model->getTable();
            $queryBuilder->select(sprintf('%s.*', $table));
        }
    }

    protected function filterByAccess($queryBuilder, $args = []) {
        // $queryBuilder->where('account_id', $args['account_id']);
        return $queryBuilder;
    }

    protected function setByAccess(&$model, $args = []) {
        // $model->account_id = $args['account_id'];
        return $model;
    }
}