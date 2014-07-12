LevelabDoctrineDiscriminatorBundle
=============================

Doctrine discriminator map extension bundle, which allows to move mapping from superclass to subclasses easily

Usage
========
* **Import annotation class**

```php
use Levelab\Doctrine\DiscriminatorBundle\Annotation\DiscriminatorEntry
```

* **Then turn this...**

```php
/**
 * @DiscriminatorMap({"self" = "Parent", "child1" = "Child1", "child2" = "Child2"})
 */
class Parent { }

class Child1 extends Parent {}

class Child2 extends Parent {}
```
* **... into this**

```php
/**
 * @DiscriminatorEntry("self")
 */
class Parent { }

/**
 * @DiscriminatorEntry("child1")
 */
class Child1 extends Parent {}

/**
 * @DiscriminatorEntry("child2")
 */
class Child2 extends Parent {}
```    
* **That's it!**
