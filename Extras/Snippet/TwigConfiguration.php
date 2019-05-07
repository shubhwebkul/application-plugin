<?php

namespace Webkul\UVDesk\AppBundle\Extras\Snippet;

trait TwigConfiguration
{
    private $twigResponse = array();

    private function getTwigResponse()
    {
        return $this->twigResponse;
    }

    private function appendTwigResponse($index = '', $value = null, $flagCheck = true)
    {
        if (empty($index))
            throw new \Exception('ChatSupportController::appendTwigResponse() function expects parameter 1 to be defined.');

        if (!empty($value))
            $this->twigResponse[$index] = $value;
        elseif ($flagCheck == false)
            $this->twigResponse[$index] = null;

        return $this;
    }

    private function removeTwigResponse($index = '')
    {
        if (empty($index))
            throw new \Exception('ChatSupportController::removeTwigResponse() function expects parameter 1 to be defined.');

        if (array_key_exists($index, $this->twigResponse))
            unset($this->twigResponse[$index]);

        return $this;
    }
}

?>
