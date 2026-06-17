<?php

namespace Nwidart\Menus;

use Illuminate\Support\Arr;

class MenuItem
{
    public $id;
    public $title;
    public $url;
    public $attributes = [];
    public $icon;
    public $parent;
    public $order = 0;
    public $active = false;
    public $hidden = false;
    public $header = false;
    public $divider = false;
    protected $childs = [];

    public function __construct($builder, $id, $title, $options)
    {
        $this->id = $id;
        $this->title = $title;
        $this->attributes = Arr::get($options, 'attributes', []);
        $this->icon = Arr::get($options, 'icon');
        $this->active = Arr::get($options, 'active', false);
    }

    public function url($url, $title, $options = [])
    {
        $item = new self(null, uniqid(), $title, $options);
        $item->url = $url;
        $item->order = Arr::get($options, 'order', 0);
        $this->childs[] = $item;
        usort($this->childs, function ($a, $b) {
            return $a->order - $b->order;
        });
        return $item;
    }

    public function dropdown($title, \Closure $callback, $options = [])
    {
        $item = new self(null, uniqid(), $title, $options);
        $item->order = Arr::get($options, 'order', 0);
        $this->childs[] = $item;
        call_user_func($callback, $item);
        usort($this->childs, function ($a, $b) {
            return $a->order - $b->order;
        });
        return $item;
    }

    public function order($order)
    {
        $this->order = $order;
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getAttributes()
    {
        $attrs = '';
        foreach ($this->attributes as $key => $value) {
            $attrs .= ' ' . $key . '="' . e($value) . '"';
        }
        return $attrs;
    }

    public function getIcon()
    {
        return $this->icon ? '<i class="' . $this->icon . '"></i>' : '';
    }

    public function isActive()
    {
        return (bool) $this->active;
    }

    public function hasActiveOnChild()
    {
        foreach ($this->childs as $child) {
            if ($child->isActive() || $child->hasActiveOnChild()) {
                return true;
            }
        }
        return false;
    }

    public function hasChilds()
    {
        return count($this->childs) > 0;
    }

    public function getChilds()
    {
        return $this->childs;
    }
}
