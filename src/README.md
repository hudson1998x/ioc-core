## IOC-Core

Inversion of Control (IoC) is a design principle in software engineering that aims to decouple the flow of control in a system from the implementation details. Instead of having high-level modules directly control the lower-level modules or components, IoC transfers that responsibility to a framework or a container. This separation of concerns enhances modularity and flexibility, allowing components to be swapped out or modified without affecting the overall system. The most common use of IoC is in dependency injection, where an external entity provides the necessary dependencies to a class, rather than the class creating or looking them up itself.

Implementing IoC leads to more testable and maintainable code, as it removes direct dependencies between components, making the system more adaptable to changes. By inverting the control flow, developers can focus on writing business logic while relying on the IoC container to handle the wiring of dependencies, lifecycle management, and other cross-cutting concerns. This principle is foundational in many modern frameworks and libraries, which leverage IoC to simplify the development process and promote best practices.

### The PHP framework built for React, Vue and Svelte

This framework was designed with modularity, ease of use, readability and maintainability in mind. 

- #### Rapid Entity Generation 
  Rather than having to individually create and map an entity, to a service, to a controller; a simple tool exists to spawn entity, controller and service, with auto crud methods. 
  ```cmd
    php cli.php @build/entity --className=App\Users\User
  ```
  This then in turn generates an empty entity, service and controller, just add fields to create the entity. 

- #### Rapid frontend development
  Writing tonnes of frontend boilerplate is tedious, catchy pesky null references, map all your entities and endpoints to async TypeScript or JavaScript functions and types. 
  
  ```cmd
    # --sourceType either 'ts' or 'js'
    php cli.php @build/generate-types --sourceType=ts --outputDir=frontend/api"
  ```

- #### Dependency Injection
  With a 0 script approach (barring `index.php` and `cli.php`), All your dependencies are managed and easily accessible, Dependency injection applies all classes, simply pass a class argument in either your class constructor, context can also be passed to your constructor. Another huge benefit of using this IoC framework, is automatic object mapping, data taken from the input stream (POST,PUT,PATCH) is parsed and transformed into an object ready to read, modify or save.

- #### Event Focused
  Events are available to all entities, during the entities lifecycle, you will have 6 events fired, `BeforeCreate`, `AfterCreate`, `BeforeUpdate`, `AfterUpdate`, `BeforeDelete`, `AfterDelete`, you can tap into these events very easily:
  ```php
    <?php

        namespace App\MyProject;

        use Hudsxn\IocCore\Attribute\ListenFor;

        class ProjectEvents
        {
            #[ListenFor('BeforeCreate', Project::class)]
            public function beforeCreate(Project $entity): Project 
            {
                // example.
                $entity->UserId = $this->getUserId();
            }

            #[ListenFor('BeforeUpdate', Project::class)]
            public function beforeUpdate(Project $mutated, Project $existing): Project
            {
                if ($existing->UserId == $mutated->UserId) {
                    return $mutated;
                }
                throw new Exception("Not your object");
            }

        }
  ```

- #### Attribute First Framework
  Attribute first codebases are so much easier to maintain, and understand, an attribute holds information about a target in PHP. Attributes are gods in this framework, they work with the dependency injector to really give this framework power.