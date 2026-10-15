<?php

namespace LmsTeamsNotificator\Tests;

class SerializableUser implements \JsonSerializable
{
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array('email' => 'persona@example.com');
    }
}
