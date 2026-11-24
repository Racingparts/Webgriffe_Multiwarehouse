<?php

require __DIR__ . '/../app/code/community/Webgriffe/Multiwarehouse/Model/Delivery/Calendar.php';
require __DIR__ . '/../app/code/community/Webgriffe/Multiwarehouse/Model/Delivery/Estimate.php';
class Mage
{
    public static function getModel($alias)
    {
        if ($alias !== 'wgmulti/delivery_calendar') {
            throw new RuntimeException('Unexpected dependency.');
        }
        return new Webgriffe_Multiwarehouse_Model_Delivery_Calendar();
    }
}
$checks = 0;
function check($actual, $expected, $label)
{
    global $checks;
    ++$checks;
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . json_encode($actual));
    }
}
$calendar = new Webgriffe_Multiwarehouse_Model_Delivery_Calendar();
foreach (array(array(0, 0), array('5', '7'), array(1, 365)) as $valid) {
    check($calendar->validateInterval(...$valid), true, 'Valid interval');
}
foreach (array(array(7, 5), array(null, 7), array('', 0), array(-1, 5), array(1.5, 7), array(0, 366), array(array(), 2)) as $invalid) {
    check($calendar->validateInterval(...$invalid), false, 'Invalid interval');
}
check($calendar->validateInterval(null, ''), true, 'Optional blank interval');
foreach (array('24:00', '12:60', '9:00', '12:00:00') as $invalid) {
    check($calendar->validCutoff($invalid), false, 'Invalid cutoff');
}
check($calendar->validCutoff('12:00'), true, 'Valid cutoff');
check($calendar->excludedDates("2026-11-02\n2026-11-02,2026-12-25"), array('2026-11-02' => true, '2026-12-25' => true), 'Excluded dates deduplicated');
foreach (array('2026-02-30', 'tomorrow', "2026-01-01\0") as $invalid) {
    try {
        $calendar->excludedDates($invalid);
        throw new RuntimeException('Invalid exclusion accepted.');
    } catch (InvalidArgumentException $e) {
        ++$checks;
    }
}
$tz = new DateTimeZone('Europe/Budapest');
check($calendar->dates(array(1, 3), new DateTimeImmutable('2026-10-30 11:00', $tz), '12:00', array()), array('2026-11-02', '2026-11-04'), 'Weekend');
check($calendar->dates(array(1, 3), new DateTimeImmutable('2026-10-30 12:00', $tz), '12:00', array()), array('2026-11-03', '2026-11-05'), 'Cutoff inclusive');
check($calendar->dates(array(1, 1), new DateTimeImmutable('2026-10-30 11:00', $tz), '', array('2026-11-02' => true)), array('2026-11-03', '2026-11-03'), 'Excluded date');
check($calendar->dates(array(0, 0), new DateTimeImmutable('2026-10-31 11:00', $tz), '', array()), array('2026-11-02', '2026-11-02'), 'Weekend zero days');
check($calendar->dates(array(1, 1), new DateTimeImmutable('2026-03-27 11:00', $tz), '', array()), array('2026-03-30', '2026-03-30'), 'DST boundary');
check($calendar->dates(array(1, 1), new DateTimeImmutable('2026-12-31 11:00', $tz), '', array('2027-01-01' => true)), array('2027-01-04', '2027-01-04'), 'Year boundary');
$estimate = new Webgriffe_Multiwarehouse_Model_Delivery_Estimate();
$rows = array(array('qty' => 2, 'delivery_days_min' => 1, 'delivery_days_max' => 2), array('qty' => 10, 'delivery_days_min' => 5, 'delivery_days_max' => 7));
check($estimate->allocate($rows, 1), array(1, 2), 'Priority stock');
check($estimate->allocate($rows, 3), array(5, 7), 'Split stock follows slowest allocation');
check($estimate->allocate($rows, 13), null, 'Insufficient stock');
$rows[0]['delivery_days_min'] = null;
check($estimate->allocate($rows, 1), null, 'Missing higher priority interval cannot be skipped');
$rows[0]['qty'] = 0;
check($estimate->allocate($rows, 1), array(5, 7), 'Zero stock ignored');
$rows[0]['qty'] = -10;
check($estimate->allocate($rows, 1), array(5, 7), 'Negative stock ignored');
echo "PASS: $checks delivery interval, calendar and warehouse allocation checks.\n";
