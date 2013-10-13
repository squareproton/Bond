<?php

namespace Bond;

use RFormatter;

class LanguageAgnosticParser
{

    private $fmt;

    public function __construct( RFormatter $formatter )
    {
        $this->fmt = $formatter;
    }

    public function parse()
    {
        $this->fmt->startRoot();
        $this->fmt->startExp();
        $this->evaluateExp($expression);
        $this->fmt->endExp();
        $this->evaluate($subject);
        $this->fmt->endRoot();
        $this->fmt->flush();
    }

    public function evaluate()
    {
    }

    public function evaluateExp()
    {
    }

}
