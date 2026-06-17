<?php

namespace Nwidart\Menus;

class Menu
{
    protected static $instance;

    protected static $menus = [];

    protected $name;
    protected $items = [];

    public function __construct()
    {
        static::$instance = $this;
    }

    public static function create($name, \Closure $callback)
    {
        $menu = new MenuBuilder($name, []);
        call_user_func($callback, $menu);
        static::$menus[$name] = $menu;
        return $menu;
    }

    public static function get($name, $options = null)
    {
        return static::$menus[$name] ?? new MenuBuilder($name, (array) $options);
    }

    public static function exists($name)
    {
        return isset(static::$menus[$name]);
    }

    public static function render($name, $presenter = null)
    {
        if (!isset(static::$menus[$name])) {
            return '';
        }

        $menu = static::$menus[$name];

        if ($presenter === 'adminltecustom') {
            $presenterInstance = new \App\Http\AdminlteCustomPresenter();
        } else {
            $presenterInstance = new Presenters\Adminlte();
        }

        return $presenterInstance->render($menu);
    }

    public static function __callStatic($method, $arguments)
    {
        $instance = new static;
        return $instance->$method(...$arguments);
    }
}

class MenuBuilder
{
    protected $name;
    protected $items = [];

    public function __construct($name, $options = [])
    {
        $this->name = $name;
    }

    public function url($url, $title, $options = [])
    {
        $item = new MenuItem($this, uniqid(), $title, $options);
        $item->url = $url;
        $item->order = \Illuminate\Support\Arr::get($options, 'order', 0);
        $this->items[] = $item;
        return $item;
    }

    public function dropdown($title, \Closure $callback, $options = [])
    {
        $item = new MenuItem($this, uniqid(), $title, $options);
        $item->order = \Illuminate\Support\Arr::get($options, 'order', 0);
        $this->items[] = $item;
        call_user_func($callback, $item);
        return $item;
    }

    public function getItems()
    {
        usort($this->items, function ($a, $b) {
            return $a->order - $b->order;
        });
        return $this->items;
    }
}
