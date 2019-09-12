<?php

namespace Tests\Factories;

use Statamic\Facades\Entry;
use Statamic\Facades\Collection;
use Statamic\Contracts\Data\Entries\Collection as StatamicCollection;

class EntryFactory
{
    protected $id;
    protected $slug;
    protected $data = [];
    protected $published = true;
    protected $order;
    protected $locale = 'en';

    public function id($id)
    {
        $this->id = $id;
        return $this;
    }

    public function slug($slug)
    {
        $this->slug = $slug;
        return $this;
    }

    public function collection($collection)
    {
        $this->collection = $collection;
        return $this;
    }

    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    public function published($published)
    {
        $this->published = $published;
        return $this;
    }

    public function order($order)
    {
        $this->order = $order;
        return $this;
    }

    public function locale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    public function make()
    {
        $entry = Entry::make()
            ->locale($this->locale)
            ->collection($this->createCollection())
            ->slug($this->slug)
            ->data($this->data)
            ->published($this->published);


        if ($this->id) {
            $entry->id($this->id);
        }

        if ($this->order) {
            $entry->order($this->order);
        }

        return $entry;
    }

    public function create()
    {
        return tap($this->make())->save();
    }

    protected function createCollection()
    {
        if ($this->collection instanceof StatamicCollection) {
            return $this->collection;
        }

        return Collection::findByHandle($this->collection)
            ?? Collection::make($this->collection)
                ->sites(['en'])
                ->save();
    }
}
