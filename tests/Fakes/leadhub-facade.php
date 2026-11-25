<?php

/*
 * Stands in for `goldnead/statamic-leadhub`. The facade under the sibling's
 * real name, because that is what the bridge probes with `class_exists`. It
 * only records what it was handed; a test reads the record.
 */

namespace Goldnead\Leadhub\Facades {
    if (! class_exists(LeadHub::class)) {
        class LeadHub
        {
            /** @var list<array<string, mixed>> */
            public static array $ingested = [];

            /** Makes ingest() throw, as a sibling that failed would. */
            public static ?\Throwable $fails = null;

            /** The facade root has no ingest() method, as an older sibling. */
            public static bool $withoutIngest = false;

            public static function getFacadeRoot(): object
            {
                if (LeadHub::$withoutIngest) {
                    return new \stdClass;
                }

                return new class
                {
                    public function ingest(array $event): ?object
                    {
                        if (LeadHub::$fails !== null) {
                            throw LeadHub::$fails;
                        }

                        LeadHub::$ingested[] = $event;

                        return (object) ['id' => count(LeadHub::$ingested), 'contact_id' => 4711];
                    }
                };
            }

            public static function ingest(array $event): ?object
            {
                return self::getFacadeRoot()->ingest($event);
            }
        }
    }
}
