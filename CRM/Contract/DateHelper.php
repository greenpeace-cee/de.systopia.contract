<?php

class CRM_Contract_DateHelper {

  public static function findNextDate(int $day, string $offset = 'now'): DateTime {
    $date = new DateTime($offset);
    $one_day = new DateInterval('P1D');

    for ($i = 0; $i < 32; $i++) {
      if ((int) $date->format('d') === $day) return $date;

      $date->add($one_day);
    }

    return NULL;
  }

}

?>
