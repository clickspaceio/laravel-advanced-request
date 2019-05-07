<?php

namespace Clickspace\AdvancedRequest;

trait ControllerMethods
{

    public function index()
    {
        $request = app('request');
        $resourceOptions = $this->parseResourceOptions($request);

        $query = $this->model::query();
        if(isset($this->defaultFilter) && $this->defaultFilter){
            $query->where($this->defaultFilter['key'], $request[$this->defaultFilter['relationship']]->sid);
        }
        if(isset($this->defaultIncludes) && $this->defaultIncludes){
            $query->with($this->defaultIncludes);
        }
        $this->applyResourceOptions($query, $resourceOptions);
        $results = $query->paginate($resourceOptions['limit'])->appends($resourceOptions);

        return $this->resource::collection($results)->response(collect($resourceOptions));
    }

    public function show($id)
    {
        $request = app('request');
        $resourceOptions = $this->parseResourceOptions($request);
        $query = $this->model::where('id', $id);
        if(isset($this->defaultFilter) && $this->defaultFilter){
            $query->where($this->defaultFilter['key'], $request[$this->defaultFilter['relationship']]->sid);
        }
        if(isset($this->defaultIncludes) && $this->defaultIncludes){
            $query->with($this->defaultIncludes);
        }
        $this->applyResourceOptions($query, $resourceOptions);
        $results = $query->firstOrFail();
        return new $this->resource($results);
    }

}