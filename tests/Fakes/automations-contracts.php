<?php

/*
 * Stands in for `goldnead/statamic-automations`, which this addon does not
 * require and the suite therefore does not install.
 *
 * The same declarations as the sibling's, under its real names, because that
 * is what the trigger implements and what the bridge probes with
 * `class_exists`. Each stand-in only records what it was handed. PHPStan scans
 * this file (see phpstan.neon) so the trigger is checked against the real
 * signatures.
 */

namespace Goldnead\StatamicAutomations\Contracts {
    use Goldnead\StatamicAutomations\Context\AutomationContext;

    if (! interface_exists(AutomationNode::class)) {
        interface AutomationNode
        {
            public static function handle(): string;

            public static function label(): string;

            public static function description(): ?string;

            public static function group(): string;

            /** @return array<int, array<string, mixed>> */
            public static function schema(): array;

            public static function supportsTestMode(): bool;
        }
    }

    if (! interface_exists(AutomationTrigger::class)) {
        interface AutomationTrigger extends AutomationNode
        {
            /** @return array<string, mixed> */
            public static function outputSchema(): array;

            /** @param  array<string, mixed>  $config */
            public function matches(object|array $event, array $config): bool;

            /** @param  array<string, mixed>  $config */
            public function buildContext(object|array $event, array $config): AutomationContext;
        }
    }
}

namespace Goldnead\StatamicAutomations\Context {
    if (! class_exists(AutomationContext::class)) {
        class AutomationContext
        {
            /** @param  array<string, mixed>  $data */
            public function __construct(protected array $data = [], protected bool $testMode = false) {}

            /** @param  array<string, mixed>  $data */
            public static function make(array $data = [], bool $testMode = false): self
            {
                return new self($data, $testMode);
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return data_get($this->data, $key, $default);
            }

            /** @return array<string, mixed> */
            public function all(): array
            {
                return $this->data;
            }
        }
    }
}

namespace Goldnead\StatamicAutomations\Facades {
    if (! class_exists(Automations::class)) {
        class Automations
        {
            /** @var list<string> */
            public static array $registered = [];

            public static function getFacadeRoot(): object
            {
                return new class
                {
                    public function registerTrigger(string $handleOrClass, ?string $class = null): self
                    {
                        Automations::$registered[] = $class ?? $handleOrClass;

                        return $this;
                    }
                };
            }
        }
    }
}

namespace Goldnead\StatamicAutomations\Engine {
    if (! class_exists(TriggerDispatcher::class)) {
        class TriggerDispatcher
        {
            /** @var list<array{handle: string, event: object|array}> */
            public static array $dispatched = [];

            public function dispatch(string $triggerHandle, object|array $event): void
            {
                self::$dispatched[] = ['handle' => $triggerHandle, 'event' => $event];
            }
        }
    }
}
