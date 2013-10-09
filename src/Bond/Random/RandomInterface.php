<?php

namespace Bond\Random;

interface RandomInterface
{
    public function __invoke();
    public function last();
}