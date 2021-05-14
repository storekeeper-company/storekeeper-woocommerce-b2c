<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class Loader
{
    /**
     * @var callable
     */
    protected $loaderFn;

    /**
     * @var array
     */
    protected $stack = [];

    /**
     * @var array
     */
    protected $loadedStack = [];

    /**
     * @return callable
     */
    public function getLoaderFn()
    {
        return $this->loaderFn;
    }

    /**
     * @param callable $loaderFn
     */
    public function setLoaderFn($loaderFn)
    {
        $this->loaderFn = $loaderFn;
    }

    /**
     * @return array
     */
    public function getStack()
    {
        return $this->stack;
    }

    /**
     * @param array $stack
     */
    public function setStack($stack)
    {
        $this->stack = $stack;
    }

    /**
     * @return array
     */
    public function getLoadedStack()
    {
        return $this->loadedStack;
    }

    /**
     * @param array $loadedStack
     */
    public function setLoadedStack($loadedStack)
    {
        $this->loadedStack = $loadedStack;
    }

    /**
     * Loader constructor.
     *
     * @param callable $loaderFn
     */
    public function __construct($loaderFn = null)
    {
        if (is_null($loaderFn)) {
            $loaderFn = function ($item) {
                return $item->load();
            };
        }
        $this->loaderFn = $loaderFn;
    }

    /**
     * @param * $stackItem
     */
    public function add($stackItem)
    {
        $stack = $this->getStack();
        array_push($stack, $stackItem);
        $this->setStack($stack);
    }

    public function run()
    {
        $stack = $this->getStack();
        $loaderFn = $this->getLoaderFn();
        $loadedStack = $this->getLoadedStack();

        foreach ($stack as $item) {
            $loadResponse = $loaderFn($item);

            if (!is_null($loadResponse)) {
                array_push($loadedStack, $loadResponse);
            }
        }

        $this->setLoadedStack($loadedStack);
    }
}
