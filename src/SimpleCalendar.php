<?php
declare(strict_types=1);

namespace MarcAndreAppel\SimpleCalendar;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

/**
 * Simple Calendar
 *
 * @author Marc-André Appel <marc-andre@appel.fun>
 * @author Jesse G. Donat <donatj@gmail.com>
 * @see https://github.com/marcandreappel/simple-calendar
 * @license http://opensource.org/licenses/mit-license.php
 */
class SimpleCalendar
{
    private array $weekdays;
    private Carbon $month;
    private ?Carbon $highlight = null;
    private array $events = [];
    private int $offset = 0;

    private array $excludedDays = [];

    private array $cssClasses = [
        'calendar'     => 'simcal',
        'leading_day'  => 'simcal-lead',
        'trailing_day' => 'simcal-trail',
        'highlight'    => 'simcal-highlight',
        'event'        => 'simcal-event',
        'events'       => 'simcal-events',
        'disabled'     => 'simcal-disabled',
    ];

    private array $customAttributes = [];
    private string $htmlTableId = '';

    /**
     * @param  Carbon|string|null  $month  Carbon parsable value for the month to show
     * @param  bool|Carbon|string|null  $highlight  Set the day to mark as highlighted in the calendar
     */
    public function __construct(Carbon|null|string $month = null, bool|Carbon|null|string $highlight = null)
    {
        $this->setHighlight($highlight);
        $this->setMonth($month);
        $this->weekdays = Carbon::getDays();
    }

    /**
     * @param  Carbon|string|null  $month
     *
     * @return void
     */
    public function setMonth(Carbon|null|string $month = null): void
    {
        if ($month === null) {
            $this->month = Carbon::now()->startOfMonth();
        } else {
            $this->month = ($month instanceof Carbon) ? $month->startOfMonth() : Carbon::parse($month)->startOfMonth();
        }
    }

    /**
     * @param  bool|Carbon|string|null  $highlight  If explicitly `false` then don't highlight any date
     *
     * @return void
     */
    public function setHighlight(bool|Carbon|null|string $highlight = null): void
    {
        if ($highlight === false) {
            $this->highlight = null;
        } elseif ($highlight === true || $highlight === null) {
            $this->highlight = Carbon::now()->startOfDay();
        } else {
            $this->highlight = ($highlight instanceof Carbon) ? $highlight : Carbon::parse($highlight);
        }
    }

    /**
     * Allows for custom CSS classes
     *
     * @param  array  $classes  Map of element to class names used by the calendar.
     *
     * @example
     * ```php
     * [
     *    'calendar'     => 'simcal',
     *    'leading_day'  => 'simcal-lead',
     *    'trailing_day' => 'simcal-trail',
     *    'highlight'        => 'simcal-highlight',
     *    'event'        => 'simcal-event',
     *    'events'       => 'simcal-events',
     *    'disabled'       => 'simcal-disabled',
     * ]
     * ```
     *
     */
    public function setCssClasses(array $classes): void
    {
        foreach ($classes as $key => $value) {
            if (!array_key_exists($key, $this->cssClasses)) {
                throw new InvalidArgumentException("class '{$key}' not supported");
            }

            $this->cssClasses[$key] = $value;
        }
    }

    /**
     * Overwrites the default names for the weekdays
     *
     * @param  array<string>  $weekdays
     */
    public function setWeekdays(array $weekdays = []): void
    {
        if (!empty($weekdays) && count($weekdays) !== 7) {
            throw new InvalidArgumentException('Week day names array must have exactly 7 values');
        }

        $this->weekdays = $weekdays ? array_values($weekdays) : Carbon::getDays();
    }

    /**
     * Add an event to the calendar
     *
     * @param  string  $title  The raw HTML to place on the calendar for this event
     * @param  Carbon|string  $startDate  Date string for when the event starts
     * @param  Carbon|string|null  $endDate  Date string for when the event ends. Defaults to start date
     */
    public function addEvent(string $title, Carbon|string $startDate, Carbon|string|null $endDate = null): void
    {
        static $eventCount = 0;

        if (!$startDate instanceof Carbon) {
            $start = Carbon::parse($startDate);
        }
        if ($endDate === null) {
            $end = $start;
        } else {
            $end = ($endDate instanceof Carbon) ? $endDate : Carbon::parse($endDate);
        }

        if ($start->greaterThan($end)) {
            throw new InvalidArgumentException('The end date must be greater than the start date.');
        }

        do {
            $tDate = $start->clone();

            $this->events[$tDate->year][$tDate->month][$tDate->day][$eventCount] = $title;

            $start->addDay();
        } while ($start->lessThan($end));

        $eventCount++;
    }

    /**
     * Clear all daily events for the calendar
     */
    public function clearDailyHtml()
    {
        $this->events = [];
    }

    /**
     * Sets the first day of the week
     *
     * @param  int|string  $offset  Day the week starts on
     *
     * @example "Monday", "mon" or 0-6, where 0 is Sunday.
     */
    public function setWeekOffset(int|string $offset): void
    {
        $this->offset = $this->parseWeekday($offset);
    }

    /**
     * Set weekdays to be marked as disabled when rendered
     *
     * @param  array  $weekdays
     *
     * @return void
     */
    public function setExcludedDays(array $weekdays): void
    {
        foreach ($weekdays as $day) {
            $this->excludedDays[] = $this->parseWeekday($day);
        }
    }

    public function setCustomAttributes(array $attributes, bool $forActiveDays = false): void
    {
        $this->customAttributes             = array_merge($this->customAttributes, $attributes);
        $this->customAttributesOnActiveDays = $forActiveDays;
    }

    /**
     * Returns the generated Calendar
     *
     * @param  string  $id  HTML `id` attribute to add to the table
     *
     * @return string
     */
    public function render(?string $id = null): string
    {
        if ($id !== null) {
            $this->htmlTableId = htmlentities(str_replace(' ', '-', strip_tags($id)), ENT_QUOTES);
        }
        $month = $this->month;

        $this->rotate();

        $weekdayIndex = $this->weekdayIndex();
        $daysInMonth  = $this->month->daysInMonth;

        $html = sprintf('<table id="%s" class="%s"><thead><tr>', $this->htmlTableId, $this->cssClasses['calendar']);

        foreach ($this->weekdays as $day) {
            $html .= "<th>{$day}</th>";
        }

        $html .= <<<HTML
</tr></thead>
<tbody>
<tr>
HTML;

        $html .= str_repeat(<<<HTML
<td class="{$this->cssClasses['leading_day']}">&nbsp;</td>
HTML
            , $weekdayIndex);

        $customAttributes = $this->getCustomAttributes();

        $count = $weekdayIndex + 1;
        for ($i = 0; $i < $daysInMonth; $i++) {
            $date  = $this->month->clone()->addDays($i);
            $today = $date->toDateString();

            $classList = [];
            if ($this->highlight !== null && $date->equalTo($this->highlight)) {
                $classList[] = $this->cssClasses['highlight'];
            }
            $isActiveDay = true;
            if (in_array($date->dayOfWeek, $this->excludedDays, true)) {
                $classList[] = $this->cssClasses['disabled'];
                $isActiveDay = false;
            }

            $html .= '<td data-simcal-id="'.$today.'" class="'.implode(' ', $classList).'"'.($isActiveDay ? str_replace(':simcal_date:', $today, $customAttributes) : '').'>';
            $html .= sprintf('<time datetime="%s">%d</time>', $today, $date->day);

            $event = $this->events[$month->year][$month->month][$date->day] ?? null;
            if (is_array($event)) {
                $html .= '<div class="'.$this->cssClasses['events'].'">';
                foreach ($event as $dHtml) {
                    $html .= sprintf('<div class="%s">%s</div>', $this->cssClasses['event'], $dHtml);
                }
                $html .= '</div>';
            }

            $html .= '</td>';

            if ($count > 6) {
                $html  .= "</tr>\n".($i < $daysInMonth ? '<tr>' : '');
                $count = 0;
            }
            $count++;
        }

        if ($count !== 1) {
            $html .= str_repeat('<td class="'.$this->cssClasses['trailing_day'].'">&nbsp;</td>', 8 - $count).'</tr>';
        }

        $html .= "\n</tbody></table>\n";

        return $html;
    }

    /**
     * @param  int|string  $weekday
     *
     * @return int
     *
     * @throws InvalidArgumentException
     */
    protected function parseWeekday(int|string $weekday): int
    {
        if (is_int($weekday)) {
            if ($weekday < 0) {
                throw new InvalidArgumentException('Weekday cannot be a negative number.');
            }

            return $weekday % 7;
        }

        try {
            return Carbon::parse($weekday)->dayOfWeek;
        } catch (InvalidFormatException) {
            throw new InvalidArgumentException('Weekday must be Carbon compatible string.');
        }
    }

    private function rotate(): void
    {
        $data  = &$this->weekdays;
        $count = count($data);

        $this->offset %= $count;
        for ($i = 0; $i < $this->offset; $i++) {
            $data[] = array_shift($data);
        }
    }

    private function weekdayIndex(): int
    {
        $weekdayIndex = $this->month->startOfMonth()->dayOfWeek;
        if ($this->offset !== Carbon::SUNDAY) {
            if ($this->offset < $weekdayIndex) {
                $weekdayIndex -= $this->offset;
            } elseif ($this->offset > $weekdayIndex) {
                $weekdayIndex += 7 - $this->offset;
            } else {
                $weekdayIndex = 0;
            }
        }

        return $weekdayIndex;
    }

    /**
     * @return string[]
     */
    public function getWeekdays(): array
    {
        return $this->weekdays;
    }

    /**
     * @return string
     */
    public function getCustomAttributes(): string
    {
        $customAttributes = '';

        if (count($this->customAttributes) > 0) {
            $tempAttributes = [];
            foreach ($this->customAttributes as $attribute => $value) {
                $attributeString  = htmlentities(
                        str_replace(' ', '-',
                            strip_tags($attribute)
                        ), ENT_QUOTES).'="'.htmlentities($value, ENT_QUOTES).'"';
                $tempAttributes[] = $attributeString;
            }
            $customAttributes = ' '.implode(' ', $tempAttributes);
        }

        return $customAttributes;
    }
}
