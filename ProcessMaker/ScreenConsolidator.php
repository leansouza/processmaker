<?php

namespace ProcessMaker;

use ProcessMaker\Exception\MaximumRecursionException;
use ProcessMaker\Models\Screen;

class ScreenConsolidator {
    private $screen;
    private $watchers = [];
    private $computed = [];
    private $custom_css = '';
    private $recursion = 0;
    private $additionalPages = [];
    private $inNestedScreen = false;

    public function __construct($screen)
    {
        $this->screen = $screen;
    }

    public function call()
    {
        if (is_array($this->screen->watchers)) {
            $this->watchers = $this->screen->watchers;
        }
        
        if (is_array($this->screen->computed)) {
            $this->computed = $this->screen->computed;
        }

        if ($this->screen->custom_css) {
            $this->custom_css = $this->screen->custom_css;
        }

        $this->additionalPages = [];
        $config = $this->replace($this->screen->config);
        foreach($this->additionalPages as $page) {
            $config[] = $page;
        }

        return [
            'config' => $config,
            'watchers' => $this->watchers,
            'custom_css' => $this->custom_css,
            'computed' => $this->computed,
        ];
    }

    public function replace($items, $index0 = 0)
    {
        $new = [];
        foreach ($items as $item) {
            if ($this->inNestedScreen && $this->is('FormButton', $item)) {
                continue;
            }
            if ($this->is('FormMultiColumn', $item)) {
                $new[] = $this->getMultiColumn($item, $index0);

            } elseif ($this->is('FormNestedScreen', $item)) {
                $this->setNestedScreen($item, $new, $index0);

            } elseif ($this->is('FormRecordList', $item)) {
                $this->setRecordList($item, $new, $index0);

            } elseif ($this->hasItems($item)) {
                $new[] = $this->getWithItems($item, $index0);

            } else {
                $new[] = $item;
            }
        }
        return $new;
    }

    private function setNestedScreen($item, &$new, $index0 = 0)
    {
        if ($this->recursion > 10) {
            throw new MaximumRecursionException();
        }
        $this->recursion++;

        $topLevelNestedScreen = false;
        if (!$this->inNestedScreen) {
            $this->inNestedScreen = true;
            $topLevelNestedScreen = true;
        }

        $screenId = $item['config']['screen'];
        $screen = Screen::findOrFail($screenId);

        $this->appendWatchers($screen);
        $this->appendComputed($screen);
        $this->appendCustomCss($screen);

        // $index0 used to unshift page references in nested screens
        // @todo: If the same nested screen is inserted multiple times it repeats the subpages,
        // it could be improved appending them once
        $index0 = count($this->screen->config) + count($this->additionalPages) - 1;
        foreach($screen->config as $index => $page) {
            if ($index === 0) {
                foreach ($this->replace($page['items'], $index0) as $screenItem) {
                    $new[] = $screenItem;
                }
            } else {
                $this->additionalPages[] = $this->getWithItems($page, $index0);
            }
        }

        if ($topLevelNestedScreen) {
            $this->inNestedScreen = false;
        }

        $this->recursion = 0;
    }

    private function setRecordList($item, &$new, $index0 = 0)
    {
        $pageId = $item['config']['form'];
        $item['config']['form'] = $pageId + $index0;
        $new[] = $item;

        $this->recursion = 0;
    }

    private function is($component, $item) {
        return is_array($item) &&
               isset($item['component']) &&
               $item['component'] === $component;
    }

    private function hasItems($item) {
        return is_array($item) && isset($item['items']);
    }

    private function getMultiColumn($item, $index0 = 0)
    {
        $new = $item;
        $newItems = [];
        foreach ($item['items'] as $column) {
            $newItems[] = $this->replace($column, $index0);
        }
        $new['items'] = $newItems;
        return $new;
    }

    private function getWithItems($item, $index0 = 0)
    {
        $new = $item;
        $new['items'] = $this->replace($item['items'], $index0);
        return $new;
    }

    private function appendWatchers($screen)
    {
        if (!is_array($screen->watchers)) {
            return;
        }

        foreach ($screen->watchers as $watcher) {
            $this->watchers[] = $watcher;
        }
        
    }

    private function appendComputed($screen) 
    {
        if (!is_array($screen->computed)) {
            return;
        }

        $id = $this->computedMaxId();
        foreach ($screen->computed as $computed) {
            $id++;
            $computed['id'] = $id;
            $this->computed[] = $computed;
        }
    }

    private function appendCustomCss($screen)
    {
        if ($screen->custom_css) {
            $this->custom_css .= "\n" . $screen->custom_css;
        }
    }

    private function computedMaxId()
    {
        if (!$this->computed) {
            return 0;
        }
        return collect($this->computed)->max('id');
    }

}