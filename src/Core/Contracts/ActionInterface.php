<?php

namespace OnaOnbir\OOAutoWeave\Core\Contracts;

interface ActionInterface
{
    /**
     * @param array $parameters   Action'a ait ayarlar
     * @param array $context      Model'den ya da manuel gelen veriler
     */
    public function execute(array $parameters, array $context = []): void;
}
