<?php

namespace Statamic\Assets;

use Statamic\Facades\Arr;
use Statamic\Facades\Path;
use Statamic\Facades\YAML;
use Statamic\Facades\Action;
use Statamic\Data\DataFolder;
use Statamic\Facades\AssetContainer;
use Illuminate\Contracts\Support\Arrayable;
use Statamic\Events\Data\AssetFolderDeleted;
use Statamic\Support\Traits\FluentlyGetsAndSets;
use Statamic\Contracts\Assets\AssetFolder as Contract;

class AssetFolder implements Contract, Arrayable
{
    use FluentlyGetsAndSets;

    protected $container;
    protected $path;
    protected $withActions = false;

    public static function find($reference)
    {
        list($container, $path) = explode('::', $reference);

        return (new static)
            ->container(AssetContainer::find($container))
            ->path($path);
    }

    public function container($container = null)
    {
        return $this->fluentlyGetOrSet('container')->args(func_get_args());
    }

    public function path($path = null)
    {
        return $this->fluentlyGetOrSet('path')->args(func_get_args());
    }

    public function withActions()
    {
        $this->withActions = true;

        return $this;
    }

    public function basename()
    {
        return pathinfo($this->path(), PATHINFO_BASENAME);
    }

    public function title($title = null)
    {
        return $this
            ->fluentlyGetOrSet('title')
            ->getter(function ($title) {
                return $title ?? $this->computedTitle();
            })
            ->args(func_get_args());
    }

    protected function computedTitle()
    {
        return pathinfo($this->path(), PATHINFO_FILENAME);
    }

    public function disk()
    {
        return $this->container()->disk();
    }

    public function resolvedPath()
    {
        return Path::tidy($this->container()->diskPath() . '/' . $this->path());
    }

    public function count()
    {
        return $this->assets()->count();
    }

    public function assets($recursive = false)
    {
        return $this->container()->assets($this->path(), $recursive);
    }

    public function lastModified()
    {
        $date = null;

        foreach ($this->assets() as $asset) {
            $modified = $asset->lastModified();

            if ($date) {
                if ($modified->gt($date)) {
                    $date = $modified;
                }
            } else {
                $date = $modified;
            }
        }

        return $date;
    }

    public function save()
    {
        $path = $this->path() . '/folder.yaml';

        if ($this->title === $this->computedTitle()) {
            $this->disk()->delete($path);
            return $this;
        }

        $this->disk()->makeDirectory($this->path());

        $arr = Arr::removeNullValues(['title' => $this->title]);

        if (! empty($arr)) {
            $this->disk()->put($path, YAML::dump($arr));
        }

        return $this;
    }

    public function delete()
    {
        $paths = [];

        // Iterate over all the assets in the folder, recursively.
        foreach ($this->assets(true) as $asset) {
            // Keep track of the paths for the event
            $paths[] = $asset->path();

            // Delete the asset. It'll remove its own data.
            $asset->delete();
        }

        // Delete the actual folder that'll be leftover. It'll include any empty subfolders.
        $this->disk()->delete($this->path());

        event(new AssetFolderDeleted($this->container(), $this->path(), $paths));

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function parent()
    {
        if ($this->path() === '/') {
            return null;
        }

        $path = Path::popLastSegment($this->path());
        $path = ($path === '') ? '/' : $path;

        return $this->container()->assetFolder($path);
    }

    /**
     * Get the folder represented as an array
     *
     * @return array
     */
    public function toArray()
    {
        $array = [
            'title' => $this->title(),
            'path' => $this->path(),
            'parent_path' => optional($this->parent())->path(),
            'basename' => $this->basename(),
        ];

        if ($this->withActions) {
            $this->withActions = false; // This is necessary to prevent infinite recursion.
            $array['actions'] = Action::for('asset-folders', ['container' => $this->container()->handle()], $this);
        }

        return $array;
    }
}
