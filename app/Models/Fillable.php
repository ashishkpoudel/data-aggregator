<?php

namespace App\Models;

trait Fillable
{

    protected $hasSourceDates = true;

    /**
     * Fill in this model's fields from the given resource, or fill it in with fake data.
     * This method is used primarily when the given resource is provided by the source
     * system.
     *
     * @param  object  $source
     * @param  bool  $fake
     * @return $this
     */
    public function fillFrom($source)
    {
        $this
            ->fillIdsFrom($source)
            ->fillTitleFrom($source)
            ->fill( $this->getFillFieldsFrom($source) );

        if( $this->hasSourceDates )
        {
            $this->fillDatesFrom($source);
        }

        return $this;
    }


    /**
     * Method to allow child classes to define how `fill` methods should treat related models
     * for each model.
     *
     * @param  object  $source
     * @return $this
     */
    public function attachFrom($source)
    {

        return $this;

    }


    /**
     * Method to allow child classes to define how `fill` methods should treat fields that are
     * specific to each model. If not overwritten, defaults to filling with all fields that do
     * not contain array or object values, except `title` and `id`, which are handled separately.
     *
     * @param  object  $source
     * @return $this
     */
    protected function getFillFieldsFrom($source)
    {

        // Ignore `id` and `title`
        foreach( ['id', 'title'] as $field )
        {
            if( isset( $source->$field ) )
            {
                unset( $source->$field );
            }
        }

        // Cast the object to an array
        $data = (array) $source;

        // Remove any fields that are objects or arrays
        $data = array_filter( $data, function( $datum ) {
            return !is_array( $datum ) && !is_object( $datum );
        });

        return $data;

    }


    /**
     * Fill in this model's identifiers from source data.
     * Meant to be overridden, especially by CollectionsModel, etc.
     *
     * @param  object  $source
     * @return $this
     */
    protected function fillIdsFrom($source)
    {

        $this->id = $source->id;

        return $this;

    }


    /**
     * Fill in this model's title from source data.
     * Meant to be overridden for more complex cases.
     *
     * @param  object  $source
     * @return $this
     */
    protected function fillTitleFrom($source)
    {

        $this->title = $source->title;

        return $this;

    }


    /**
     * Fill in this model's dates from the given resource, or fill it in with fake data.
     * This method is used primarily when the given resource is provided by the source
     * system.
     *
     * @param  object  $source
     * @return $this
     */
    protected function fillDatesFrom($source)
    {

        $fill = [];

        $fill['source_created_at'] = strtotime($source->created_at);
        $fill['source_modified_at'] = strtotime($source->modified_at);

        $this->fill($fill);

        return $this;

    }


}
