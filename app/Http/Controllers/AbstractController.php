<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Input;
use Closure;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;

use Aic\Hub\Foundation\ResourceSerializer;
use Aic\Hub\Foundation\Exceptions\BigLimitException;
use Aic\Hub\Foundation\Exceptions\InvalidSyntaxException;
use Aic\Hub\Foundation\Exceptions\ItemNotFoundException;
use Aic\Hub\Foundation\Exceptions\TooManyIdsException;

use Aic\Hub\Foundation\AbstractController as BaseController;

abstract class AbstractController extends BaseController
{

    /**
     * @var \League\Fractal\Manager
     */
    private $fractal;

    public function __construct()
    {
        $this->fractal = app()->make('League\Fractal\Manager');
        $this->fractal->setSerializer(new ResourceSerializer);

        // Parse fractal includes and excludes
        $this->parseFractalParam('include', 'parseIncludes');
        $this->parseFractalParam('exclude', 'parseExcludes');
    }


    /**
     * Helper to parse Fractal includes or excludes.
     *
     * @link http://fractal.thephpleague.com/transformers/
     *
     * @param \League\Fractal\Manager $fractal
     * @param string $param  Name of query string param to parse
     * @param string $method  Either `parseIncludes` or `parseExcludes`
     */
    private function parseFractalParam( $param, $method )
    {
        $values = Input::get($param);

        if(!isset($values))
        {
            return;
        }

        // Fractal handles this internally, but we do it early for preprocessing
        if(is_string($values))
        {
            $values = explode(',', $values);
        }

        // Allows for camel, snake, and kebab cases
        foreach($values as &$value)
        {
            $value = snake_case(camel_case($value));
        }

        $this->fractal->$method($values);
    }


    /**
     * Return a response with a single resource, given an Eloquent Model.
     *
     * @param  \Illuminate\Database\Eloquent\Model $item
     * @return \Illuminate\Http\Response
     */
    protected function getItemResponse(Model $item)
    {
        return response()->json(
            $this->getGenericResponse($item, Item::class)
        );
    }


    /**
     * Return a response with multiple resources, given an arrayable object.
     * For multiple ids, this is a an Eloquent Collection.
     * For pagination, this is LengthAwarePaginator.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable $collection
     * @return \Illuminate\Http\Response
     */
    protected function getCollectionResponse(Arrayable $collection)
    {
        $response = $this->getGenericResponse($collection, Collection::class);

        if ($collection instanceof LengthAwarePaginator)
        {
            $paginator = [
                'total' => $collection->total(),
                'limit' => (int) $collection->perPage(),
                'offset' => (int) $collection->perPage() * ( $collection->currentPage() - 1 ),
                'total_pages' => $collection->lastPage(),
                'current_page' => $collection->currentPage(),
            ];

            if ($collection->previousPageUrl()) {
                $paginator['prev_url'] = $collection->previousPageUrl() . '&limit=' . $collection->perPage();
            }

            if ($collection->hasMorePages()) {
                $paginator['next_url'] = $collection->nextPageUrl() . '&limit=' . $collection->perPage();
            }

            $response = ['pagination' => $paginator] + $response;
        }

        return response()->json($response);
    }

    /**
     * Helper to fill data and attach metadata for items and collections.
     * @param  \Illuminate\Contracts\Support\Arrayable $inputData
     * @param  string $resourceClass  Must implement \League\Fractal\Resource\ResourceAbstract
     * @return array
     */
    protected function getGenericResponse(Arrayable $inputData, string $resourceClass)
    {
        $fields = Input::get('fields');
        $transformer = new $this->transformer($fields);
        $resource = new $resourceClass($inputData, $transformer);
        $data = $this->fractal->createData($resource)->toArray();
        $response = isset($data['data']) ? $data : ['data' => $data];

        $info = [
            'version' => config('app.version')
        ];
        if (config('app.documentation_url'))
        {
            $info['documentation'] = config('app.documentation_url');
        }
        if (config('app.message'))
        {
            $info['message'] = config('app.message');
        }

        $response['info'] = $info;

        $config = config('app.config_documentation');

        if ($config)
        {
            $response['config'] = $config;
        }

        return $response;
    }


    /**
     * Return a single resource. Not meant to be called directly in routes.
     * `$callback` should return an Eloquent Model.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $callback
     * @return \Illuminate\Http\Response
     */
    protected function select( Request $request, Closure $callback )
    {

        $this->validateMethod( $request );

        $id = $request->route('id');

        if (!$this->validateId( $id ))
        {
            throw new InvalidSyntaxException();
        }

        $item = $callback( $id );

        if (!$item)
        {
            throw new ItemNotFoundException();
        }

        return $this->getItemResponse($item);

    }


    /**
     * Return a list of resources. Not meant to be called directly in routes.
     * `$callback` should return LengthAwarePaginator.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $callback
     * @return \Illuminate\Http\Response
     */
    protected function collect( Request $request, Closure $callback )
    {

        $this->validateMethod( $request );

        // Process ?ids= query param
        $ids = $request->input('ids');

        if ($ids)
        {
            return $this->showMutliple($ids);
        }

        // Check if the ?limit= is too big
        $limit = $request->input('limit') ?: 12;

        if ($limit > static::LIMIT_MAX)
        {
            throw new BigLimitException();
        }

        // This would happen for subresources
        $id = $request->route('id');

        // Assumes the inheriting class set model and transformer
        // \Illuminate\Contracts\Pagination\LengthAwarePaginator
        $all = $callback( $limit, $id );

        return $this->getCollectionResponse($all);

    }


    /**
     * Display multiple resources.
     *
     * @param string $ids
     * @return \Illuminate\Http\Response
     */
    protected function showMutliple($ids = '')
    {

        // TODO: Accept an array, not just comma-separated string
        $ids = explode(',', $ids);

        if (count($ids) > static::LIMIT_MAX)
        {
            throw new TooManyIdsException();
        }

        // Validate the syntax for each $id
        foreach( $ids as $id )
        {

            if (!$this->validateId( $id ))
            {
                throw new InvalidSyntaxException();
            }

        }

        // Illuminate\Database\Eloquent\Collection
        $all = $this->find($ids);

        return $this->getCollectionResponse($all);

    }

}
