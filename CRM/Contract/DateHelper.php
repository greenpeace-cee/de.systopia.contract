<?php

class CRM_Contract_DateHelper {

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

}

?>
