<?php

declare(strict_types=1);

namespace Erenav\LaravelICalendar\Persistence;

use Erenav\ICalendar\Component\Calendar as CoreCalendar;
use Erenav\ICalendar\Component\Component;
use Erenav\ICalendar\Component\Event;
use Erenav\ICalendar\Parser\Parser;
use Erenav\ICalendar\Serializer\IcsSerializer;
use UnexpectedValueException;

final class CoreComponentCodec
{
    public function __construct(
        private readonly Parser $parser = new Parser,
        private readonly IcsSerializer $serializer = new IcsSerializer,
    ) {}

    public function encode(Component $component): string
    {
        return $this->serializer->serialize($component);
    }

    public function decodeEvent(string $ics): Event
    {
        $component = $this->parser->parse($ics);
        if (! $component instanceof Event) {
            throw new UnexpectedValueException('Stored component is not a VEVENT.');
        }

        return $component;
    }

    public function encodeCalendarEnvelope(CoreCalendar $calendar): string
    {
        // Keep the complete source tree as an ordering template. VEVENT rows are
        // still authoritative and are replaced/removed by the exporter.
        return $this->encode($calendar);
    }

    public function decodeCalendarEnvelope(?string $ics): CoreCalendar
    {
        if ($ics === null || $ics === '') {
            return CoreCalendar::build()->get();
        }

        return $this->parser->parseCalendar($ics);
    }
}
