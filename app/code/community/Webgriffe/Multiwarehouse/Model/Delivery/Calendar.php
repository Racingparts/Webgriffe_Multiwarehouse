<?php

class Webgriffe_Multiwarehouse_Model_Delivery_Calendar
{
    public function interval($min, $max): ?array
    {
        if ($min === null || $max === null || $min === '' || $max === '' || !is_scalar($min) || !is_scalar($max)) {
            return null;
        }
        if (!preg_match('/^\d{1,3}$/D', (string)$min) || !preg_match('/^\d{1,3}$/D', (string)$max)
            || (int)$min > (int)$max || (int)$max > 365) {
            return null;
        }
        return array((int)$min, (int)$max);
    }

    public function validateInterval($min, $max): bool
    {
        return (($min === '' || $min === null) && ($max === '' || $max === null)) || $this->interval($min, $max) !== null;
    }

    public function excludedDates(string $value): array
    {
        if (strpos($value, "\0") !== false) {
            throw new InvalidArgumentException('Excluded dates must use valid YYYY-MM-DD dates.');
        }
        $dates = preg_split('/[\s,;]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        if (count($dates) > 1000) {
            throw new InvalidArgumentException('At most 1000 excluded dates are allowed.');
        }
        $result = array();
        foreach ($dates as $date) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
                throw new InvalidArgumentException('Excluded dates must use valid YYYY-MM-DD dates.');
            }
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Excluded dates must use valid YYYY-MM-DD dates.');
            }
            $result[$date] = true;
        }
        return $result;
    }

    public function validCutoff(string $cutoff): bool
    {
        return $cutoff === '' || (bool)preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $cutoff);
    }

    public function dates(array $interval, DateTimeImmutable $now, string $cutoff, array $excluded): array
    {
        if (!$this->validCutoff($cutoff) || $this->interval($interval[0] ?? null, $interval[1] ?? null) === null) {
            throw new InvalidArgumentException('Invalid delivery calendar settings.');
        }
        $start = $now->setTime(0, 0);
        if ($cutoff !== '' && $now->format('H:i') >= $cutoff) {
            $start = $start->modify('+1 day');
        }
        while (!$this->isBusinessDay($start, $excluded)) {
            $start = $start->modify('+1 day');
        }
        return array($this->addDays($start, $interval[0], $excluded), $this->addDays($start, $interval[1], $excluded));
    }

    protected function isBusinessDay(DateTimeImmutable $date, array $excluded): bool
    {
        return (int)$date->format('N') <= 5 && !isset($excluded[$date->format('Y-m-d')]);
    }

    protected function addDays(DateTimeImmutable $date, int $days, array $excluded): string
    {
        while ($days > 0) {
            $date = $date->modify('+1 day');
            if ($this->isBusinessDay($date, $excluded)) {
                --$days;
            }
        }
        return $date->format('Y-m-d');
    }
}
