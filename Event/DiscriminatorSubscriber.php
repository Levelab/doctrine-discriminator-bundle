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
    private $map;

    private $cachedMap;

    const ENTRY_ANNOTATION = 'Levelab\Doctrine\DiscriminatorBundle\Annotation\DiscriminatorEntry';

    public function __construct()
    {
        $this->cachedMap = array();
    }

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
        // Reset the temporary calculation map and get the classname
        $this->map = array();
        $class = $event->getClassMetadata()->name;
        $driver = $event->getEntityManager()->getConfiguration()->getMetadataDriverImpl();

        // Did we already calculate the map for this element?
        if(array_key_exists($class, $this->cachedMap))
        {
            $this->overrideMetadata($event, $class);
            return;
        }

        // Do we have to process this class?
        if($this->extractEntry($class))
        {
            // Now build the whole map
            $this->checkFamily($class, $driver);
        }
        else
        {
            // Nothing to do…
            return;
        }

        // Create the lookup entries
        $dMap = array_flip($this->map);
        foreach($this->map as $cName => $discr)
        {
            $this->cachedMap[$cName]['map'] = $dMap;
            $this->cachedMap[$cName]['discr'] = $this->map[$cName];
        }
        // Override the data for this class
        $this->overrideMetadata($event, $class);
    }

    private function overrideMetadata(LoadClassMetadataEventArgs $event, $class)
    {
        // Set the discriminator map and value
        $event->getClassMetadata()->discriminatorMap = $this->cachedMap[$class]['map'];
        $event->getClassMetadata()->discriminatorValue = $this->cachedMap[$class]['discr'];

        // If we are the top-most parent, set subclasses!
        if(isset($this->cachedMap[$class]['isParent']) && $this->cachedMap[$class]['isParent'] === true)
        {
            $subclasses = $this->cachedMap[$class]['map'];
            unset($subclasses[$this->cachedMap[$class]['discr']]);

            $event->getClassMetadata()->subClasses = array_values($subclasses);
        }
    }

    private function checkFamily($class, $driver)
    {
        $rc = new \ReflectionClass($class);
        $prc = $rc->getParentClass();

        if($prc !== false)
        {
            // Also check all the children of our parent
            $this->checkFamily($prc->name, $driver);
        }
        else
        {
            // This is the top-most parent, used in overrideMetadata
            $this->cachedMap[$class]['isParent'] = true;
            // Find all the children of this class
            $this->checkChildren($class, $driver);
        }
    }

    private function checkChildren($class, $driver)
    {
        foreach($driver->getAllClassNames() as $name)
        {
            $cRc = new \ReflectionClass($name);
            $cRcparent = $cRc->getParentClass();

            if(!$cRcparent)
                continue;

            // Haven't done this class yet? Go for it.
            if(!array_key_exists($name, $this->map) && $cRcparent->name == $class && $this->extractEntry($name))
            {
                $this->checkChildren($name, $driver);
            }
        }
    }

    private function extractEntry($class)
    {
        $rc = new \ReflectionClass($class);
        $reader = new AnnotationReader();

        $annotation = $reader->getClassAnnotation($rc, self::ENTRY_ANNOTATION);
        $success = false;

        if(!is_null($annotation))
        {
            $value = $annotation->getValue();

            if(in_array($value, $this->map))
            {
                throw new \Exception("Found duplicate discriminator map entry '" . $value . "' in " . $class);
            }

            $this->map[$class] = $value;
            $success = true;
        }

        return $success;
    }
}