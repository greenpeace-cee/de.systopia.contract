<?php

class CRM_Contract_DateHelper {

  public static function findLastOfDays (array $days, string $offset = 'now') {
    if (empty($days)) return NULL;

    $date = new DateTime($offset);
    $one_day = new DateInterval('P1D');

    for ($i = 0; $i < 32; $i++) {
      if (in_array((int) $date->format('d'), $days, TRUE)) return $date;

      $date->sub($one_day);
    }

    return NULL;
  }

  public static function findNextOfDays(array $days, string $offset = 'now') {
    if (empty($days)) return NULL;

    $date = new DateTime($offset);
    $one_day = new DateInterval('P1D');

    for ($i = 0; $i < 32; $i++) {
      if (in_array((int) $date->format('d'), $days, TRUE)) return $date;

      $date->add($one_day);
    }

    return NULL;
  }

  public static function minimumChangeDate(string $offset = 'now') {
    $min_date = Civi::settings()->get('contract_minimum_change_date');
    $offset_date = new DateTimeImmutable($offset);
    $min_date = empty($min_date) ? $offset_date : new DateTimeImmutable($min_date);

    return max($offset_date, $min_date);
  }

  public static function nextRegularDate(string $offset, int $interval, string $unit) {
    $date = new DateTime($offset);
    $one_day = new DateInterval('P1D');

    if ($unit === 'month') {
      // Adding months can lead to skipping some months:
      //
      //   31st January + 1 month = 3rd March
      //
      // To avoid this the initial date will be shifted back to the 28th
      while ((int) $date->format('d') > 28) $date->sub($one_day);
    }

    $designator = [
      'day'   => 'D',
      'week'  => 'W',
      'month' => 'M',
      'year'  => 'Y',
    ][$unit];

    $covered_period = new DateInterval("P{$interval}{$designator}");
    $date->add($covered_period);

    return $date;
  }

}

?>
