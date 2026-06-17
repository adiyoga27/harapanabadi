<?php

namespace Nwidart\Menus\Presenters;

abstract class Presenter
{
    abstract public function getOpenTagWrapper();

    abstract public function getCloseTagWrapper();

    abstract public function getMenuWithoutDropdownWrapper($item);

    abstract public function getActiveState($item, $state = ' class="active"');

    abstract public function getActiveStateOnChild($item, $state = 'active');

    abstract public function getDividerWrapper();

    abstract public function getHeaderWrapper($item);

    abstract public function getMenuWithDropDownWrapper($item);

    abstract public function getMultiLevelDropdownWrapper($item);

    public function render($menu)
    {
        $html = $this->getOpenTagWrapper();

        foreach ($menu->getItems() as $item) {
            if ($item->hidden) {
                continue;
            }

            if ($item->hasChilds()) {
                $html .= $this->getMenuWithDropDownWrapper($item);
            } elseif ($item->header) {
                $html .= $this->getHeaderWrapper($item);
            } elseif ($item->divider) {
                $html .= $this->getDividerWrapper();
            } else {
                $html .= $this->getMenuWithoutDropdownWrapper($item);
            }
        }

        $html .= $this->getCloseTagWrapper();

        return $html;
    }

    public function getChildMenuItems($item)
    {
        $results = '';
        foreach ($item->getChilds() as $child) {
            if ($child->hidden) {
                continue;
            }

            if ($child->hasChilds()) {
                $results .= $this->getMultiLevelDropdownWrapper($child);
            } elseif ($child->header) {
                $results .= $this->getHeaderWrapper($child);
            } elseif ($child->divider) {
                $results .= $this->getDividerWrapper();
            } else {
                $results .= $this->getMenuWithoutDropdownWrapper($child);
            }
        }

        return $results;
    }
}
