<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar;

use Erenav\ICalendar\Builder\CalendarBuilder;
use Erenav\ICalendar\Builder\EventBuilder;
use Erenav\ICalendar\Component\Calendar;
use Erenav\ICalendar\Component\Component;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Serializer\IcsSerializer;
use Erenav\LaravelICalendar\Contracts\ProvidesCalendarEvent;

/**
 * The service behind the `ICalendar` facade: thin, config-aware sugar over the
 * framework-agnostic core (builders, parser, serializer).
 */
class ICalendarManager
{
    /** @param array<array-key, mixed> $config */
    public function __construct(
        private readonly array $config = [],
    ) {}

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
     * @param  iterable<ProvidesCalendarEvent>  $models
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
        $default = $this->config['filename'] ?? 'calendar.ics';

        return new CalendarResponse(
            $component,
            $filename ?? (is_string($default) ? $default : 'calendar.ics'),
            $this,
        );
    }
}
