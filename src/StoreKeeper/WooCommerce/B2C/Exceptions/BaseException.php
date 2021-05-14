<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class BaseException extends \Exception
{
    public static function getAsString(\Throwable $e, $level = 0)
    {
        $text = '';
        if ($level > 0) {
            $text .= " -> Previous [$level] \n";
        }
        $text .= '['.get_class($e).'] '.$e->getMessage()." \n";
        $text .= 'in '.$e->getFile().':'.$e->getLine()."\n";
        $text .= "Stack trace: \n".$e->getTraceAsString()."\n";
        $text .= "\n";

        $previous = $e->getPrevious();
        if ($previous) {
            $text .= static::getAsString($previous, $level + 1);
        }

        return $text;
    }
}
