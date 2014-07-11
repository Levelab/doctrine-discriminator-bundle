<?php

/*
 * (c) Leonid Repin <leonid-repin@levelab.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Levelab\Doctrine\DiscriminatorBundle\Annotation;

/**
 * @Annotation
 */
class DiscriminatorEntry
{
    private $value;

    public function __construct(array $data)
    {
        $this->value = $data['value'];
    }

    public function getValue()
    {
        return $this->value;
    }
} 