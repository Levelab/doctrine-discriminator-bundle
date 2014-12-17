<?php

/*
 * (c) Leonid Repin <leonid-repin@levelab.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Levelab\Doctrine\DiscriminatorBundle\Event;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;

class DiscriminatorSubscriber implements EventSubscriber
{
    private $discriminatorMaps = array();
    private $annotations = array();

    const ENTRY_ANNOTATION = 'Levelab\Doctrine\DiscriminatorBundle\Annotation\DiscriminatorEntry';
    const DISCRIMINATOR_PARENT = 'Doctrine\ORM\Mapping\DiscriminatorMap';

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(Events::loadClassMetadata);
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event)
    {
        $class = $event->getClassMetadata()->name;
        $driver = $event->getEntityManager()->getConfiguration()->getMetadataDriverImpl();

        //
        // Is it DiscriminatorMap parent class?
        // DiscriminatorSubscriber::loadClassMetadata processes only parent classes
        //
        if (!$this->isDiscriminatorParent($class)) {
            return;
        }

        //
        // Register our discriminator class
        //
        $this->discriminatorMaps[$class] = array();

        //
        // And find all subclasses for this parent class
        //
        foreach ($driver->getAllClassNames() as $name) {
            if ($this->isDiscriminatorChild($class, $name)) {
                $this->discriminatorMaps[$class][] = $name;
            }
        }

        //
        // Collect $discriminatorMap for ClassMetadata
        //
        $discriminatorMap = array();
        foreach ($this->discriminatorMaps[$class] as $childClass) {
            $annotation = $this->getAnnotation(new \ReflectionClass($childClass), self::ENTRY_ANNOTATION);

            $discriminatorMap[$annotation->getValue()] = $childClass;
        }

        $parentAnnotation = $this->getAnnotation(new \ReflectionClass($class), self::ENTRY_ANNOTATION);

        //
        // $discriminatorValue can be null ot not
        //
        $discriminatorValue = $parentAnnotation->getValue();
        if ($discriminatorValue !== null) {
            $discriminatorMap[$discriminatorValue] = $class;
        }

        $event->getClassMetadata()->discriminatorValue = $discriminatorValue;
        $event->getClassMetadata()->discriminatorMap = $discriminatorMap;
    }

    /**
     * @param \ReflectionClass $class
     * @param $annotationName
     * @return mixed
     */
    private function getAnnotation(\ReflectionClass $class, $annotationName)
    {
        if (isset($this->annotations[$class->getName()][$annotationName])) {
            return $this->annotations[$class->getName()][$annotationName];
        }

        $reader = new AnnotationReader();

        if ($annotation = $reader->getClassAnnotation($class, $annotationName)) {
            $this->annotations[$class->getName()][$annotationName] = $annotation;
        }

        return $annotation;
    }

    /**
     * @param string $class
     * @return bool
     */
    private function isDiscriminatorParent($class)
    {
        $reflectionClass = new \ReflectionClass($class);

        if (!$this->getAnnotation($reflectionClass, self::DISCRIMINATOR_PARENT)) {
            return false;
        }

        if (!$this->getAnnotation($reflectionClass, self::ENTRY_ANNOTATION)) {
            return false;
        }

        return true;
    }

    /**
     * @param string $parent
     * @param string $class
     * @return bool
     */
    private function isDiscriminatorChild($parent, $class)
    {
        $reflectionClass = new \ReflectionClass($class);
        $parentClass = $reflectionClass->getParentClass();

        if ($parentClass === false) {
            return false;
        } elseif ($parentClass->getName() !== $parent) {
            return $this->isDiscriminatorChild($parentClass->getName(), $class);
        }

        if ($this->getAnnotation($reflectionClass, self::ENTRY_ANNOTATION)) {
            return true;
        }

        return false;
    }
}