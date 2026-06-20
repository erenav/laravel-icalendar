<?php

declare(strict_types=1);

namespace Vanere\LaravelICalendar;

use Vanere\ICalendar\Builder\CalendarBuilder;
use Vanere\ICalendar\Builder\EventBuilder;
use Vanere\ICalendar\Component\Calendar;
use Vanere\ICalendar\Component\Component;
use Vanere\ICalendar\Component\Event;
use Vanere\ICalendar\Parser\Parser;
use Vanere\ICalendar\Serializer\IcsSerializer;
use Vanere\LaravelICalendar\Contracts\ProvidesCalendarEvent;

/**
 * The service behind the `ICalendar` facade: thin, config-aware sugar over the
 * framework-agnostic core (builders, parser, serializer).
 */
class ICalendarManager
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config = [],
    ) {
    }

    /** A calendar builder pre-stamped with the configured PRODID. */
    public function calendar(): CalendarBuilder
    {
        $builder = Calendar::build();
        $productId = $this->config['product_id'] ?? null;
        if (is_string($productId) && $productId !== '') {
            $builder->prodId($productId);
        }

        return $builder;
    }

    public function event(): EventBuilder
    {
        return Event::build();
    }

    public function parse(string $ics): Calendar
    {
        $parser = ($this->config['strict'] ?? false) ? Parser::strict() : Parser::lenient();

        return $parser->parseCalendar($ics);
    }

    public function serialize(Component $component): string
    {
        if (($this->config['include_timezones'] ?? true) && $component instanceof Calendar) {
            $component = $component->withTimeZones();
        }

        return (new IcsSerializer((bool) ($this->config['strict'] ?? false)))->serialize($component);
    }

    /**
     * Build a calendar from a collection of models that map themselves to events.
     *
     * @param iterable<ProvidesCalendarEvent> $models
     */
    public function fromModels(iterable $models): Calendar
    {
        $builder = $this->calendar();
        foreach ($models as $model) {
            $builder->add($model->toCalendarEvent());
        }

        return $builder->get();
    }

    /** Wrap a component in a downloadable text/calendar HTTP response. */
    public function response(Component $component, ?string $filename = null): CalendarResponse
    {
        return new CalendarResponse(
            $component,
            $filename ?? (string) ($this->config['filename'] ?? 'calendar.ics'),
            $this,
        );
    }
}
