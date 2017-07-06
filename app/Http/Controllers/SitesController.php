<?php

namespace App\Http\Controllers;

use App\StaticArchive\Site;
use Illuminate\Http\Request;

class SitesController extends ApiController
{

    /**
     * Display a listing of the resource.
     *
     * @param null $id
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if ($request->method() != 'GET')
        {

            $this->respondMethodNotAllowed();

        }

        $ids = $request->input('ids');
        if ($ids)
        {

            return $this->showMutliple($ids);

        }

        $limit = $request->input('limit') ?: 12;
        if ($limit > static::LIMIT_MAX) return $this->respondForbidden('Invalid limit', 'You have requested too many sites. Please set a smaller limit.');
        
        $all = Site::paginate();
        return response()->collection($all, new \App\Http\Transformers\SiteTransformer);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\StaticArchive\Site  $site
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $siteId)
    {

        if ($request->method() != 'GET')
        {

            $this->respondMethodNotAllowed();

        }

        try
        {
            if (intval($siteId) <= 0)
            {
                return $this->respondInvalidSyntax('Invalid identifier', "The site identifier should be a number. Please ensure you're passing the correct source identifier and try again.");
            }

            $item = Site::find($siteId);

            if (!$item)
            {
                return $this->respondNotFound('Site not found', "The site you requested cannot be found. Please ensure you're passing the source identifier and try again.");
            }

            return response()->item($item, new \App\Http\Transformers\SiteTransformer);
        }
        catch(\Exception $e)
        {
            return $this->respondFailure();
        }
        
    }

    public function showMutliple($ids = '')
    {

        $ids = explode(',',$ids);
        if (count($ids) > static::LIMIT_MAX)
        {
            
            return $this->respondForbidden('Invalid number of ids', 'You have requested too many ids. Please send a smaller amount.');
            
        }
        $all = Site::find($ids);
        return response()->collection($all, new \App\Http\Transformers\SiteTransformer);
        
    }

}