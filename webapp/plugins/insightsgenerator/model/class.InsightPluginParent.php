<?php
/**
 *
 * ThinkUp/webapp/plugins/insightsgenerator/model/class.InsightPluginParent.php
 *
 * Copyright (c) 2012-2015 Gina Trapani
 *
 * LICENSE:
 *
 * This file is part of ThinkUp (http://thinkup.com).
 *
 * ThinkUp is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any
 * later version.
 *
 * ThinkUp is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with ThinkUp.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @license http://www.gnu.org/licenses/gpl.html
 * @copyright 2012-2015 Gina Trapani
 * @author Gina Trapani <ginatrapani [at] gmail [dot] com>
 */
class InsightPluginParent {
    /**
     * @var InsightDAO
     */
    var $insight_dao;
    /**
     * @var Logger
     */
    var $logger;
    /**
     * @var InsightTerms
     */
    var $terms;
    /**
     * Insight date
     * @var str
     */
    var $insight_date;
    /**
     * Username.
     * @var str
     */
    var $username;
    public function generateInsight(Instance $instance, User $user, $last_week_of_posts, $number_days) {
        $this->logger = Logger::getInstance();
        $this->logger->setUsername($instance->network_username);
        $this->insight_date = date("Y-m-d");
        $this->insight_dao = DAOFactory::getDAO('InsightDAO');
        $this->username = ($instance->network == 'twitter')?'@'.$instance->network_username:$instance->network_username;
        $this->terms = new InsightTerms($instance->network);
    }

    /**
     * Determine whether an insight should be generated.
     * @param str $slug slug of the insight to be generated
     * @param Instance $instance user and network details for which the insight has to be generated
     * @param str $insight_date valid strtotime parameter for insight date, defaults to 'today'
     * @param bool $regenerate_existing_insight whether the insight should be regenerated over a day
     * @param int $count_related_posts if set, wouldn't run insight if there are no posts related to insight
     * @param arr $excluded_networks array of networks for which the insight shouldn't be run
     * @return bool Whether the insight should be generated or not
     */
    public function shouldGenerateInsight($slug, Instance $instance, $insight_date=null,
        $regenerate_existing_insight=false, $count_related_posts=null, $excluded_networks=null) {
        $run = true;

        // Always generate if testing
        if (Utils::isTest()) {
            return true;
        }

        // Check the number of related posts
        if (isset($count_related_posts)) {
            $run = $run && $count_related_posts;
        }

        // Check boolean whether insight should be regenerated over a day
        if (!$regenerate_existing_insight) {
            $insight_date = isset($insight_date) ? $insight_date : 'today';

            $existing_insight = $this->insight_dao->getInsight($slug, $instance->id,
            date('Y-m-d', strtotime($insight_date)));

            if (isset($existing_insight)) {
                $run = $run && false;
            } else {
                $run = $run && true;
            }
        }

        // Check array of networks for which the insight should run
        if (isset($excluded_networks)) {
            if (in_array($instance->network, $excluded_networks)) {
                $run = $run && false;
            } else {
                $run = $run && true;
            }
        }
        return $run;
    }

    /**
     * Determine whether a weekly insight should be generated.
     * @param str $slug slug of the insight to be generated
     * @param Instance $instance user and network details for which the insight has to be generated
     * @param str $insight_date valid strtotime parameter for insight date, defaults to 'today'
     * @param bool $regenerate_existing_insight whether the insight should be regenerated over a day
     * @param int $day_of_week the day of week (0 for Sunday through 6 for Saturday) on which the insight should run
     * @param int $count_last_week_of_posts if set, wouldn't run insight if there are no posts from last week
     * @param arr $excluded_networks array of networks for which the insight shouldn't be run
     * @return bool Whether the insight should be generated or not
     */
    public function shouldGenerateWeeklyInsight($slug, Instance $instance, $insight_date=null,
        $regenerate_existing_insight=false, $day_of_week=null, $count_last_week_of_posts=null,
        $excluded_networks=null) {
        $run = true;

        // Always generate if testing
        if (Utils::isTest()) {
            return true;
        } else {
            $run = self::shouldGenerateInsight( $slug, $instance, $insight_date, $regenerate_existing_insight,
                $count_last_week_of_posts, $excluded_networks);

            // Check the day of the week (0 for Sunday through 6 for Saturday) on which the insight should run
            if (isset($day_of_week)) {
                if (date('w') == $day_of_week) {
                    $run = $run && true;
                } else {
                    $run = $run && false;
                }
            }
        }
        return $run;
    }

    /**
     * Determine whether a monthly insight should be generated.
     * @param str $slug slug of the insight to be generated
     * @param Instance $instance user and network details for which the insight has to be generated
     * @param str $insight_date valid strtotime parameter for insight date, defaults to 'today'
     * @param bool $regenerate_existing_insight whether the insight should be regenerated over a day
     * @param int $day_of_month the day of the month on which the insight should run
     * @param int $count_related_posts if set, wouldn't run insight if there are no posts related to insight
     * @param arr $excluded_networks array of networks for which the insight shouldn't be run
     * @param bool $enable_bonus_alternate_day whether or not to run insight on alternate day 15 days from day of month
     * @return bool Whether the insight should be generated or not
     */
    public function shouldGenerateMonthlyInsight($slug, Instance $instance, $insight_date=null,
        $regenerate_existing_insight=false, $day_of_month=null, $count_related_posts=null, $excluded_networks=null,
        $enable_bonus_alternate_day = true) {
        $run = true;

        // Always generate if testing
        if (Utils::isTest()) {
            return true;
        } else {
            $run = self::shouldGenerateInsight( $slug, $instance, $insight_date, $regenerate_existing_insight,
                $count_related_posts, $excluded_networks);

            // Check the day of the month
            $right_day = true;
            if (isset($day_of_month)) {
                if (date('j') != $day_of_month) {
                    $right_day = false;
                }
            }

            // Now we check for the bonus first time alternate day of the month
            if ($run && !$right_day && $enable_bonus_alternate_day) {
                $alternate_day_of_month = (($day_of_month+15)%date('t'))+1;
                if (date('j') == $alternate_day_of_month) {
                    $owner_instance_dao = DAOFactory::getDAO('OwnerInstanceDAO');
                    $owner_dao = DAOFactory::getDAO('OwnerDAO');
                    $owner_instance = $owner_instance_dao->getByInstance($instance->id);
                    //@TODO don't assume there's only one OwnerInstance
                    $owner = $owner_dao->getById($owner_instance[0]->owner_id);
                    if ((time() - strtotime($owner->joined)) < (60*60*24*15)) {
                        $right_day = true;
                    }
                }
            }

            $run = $run && $right_day;
        }
        return $run;
    }

    /**
     * Determine whether an annual insight should be generated.
     * @param str $slug slug of the insight to be generated
     * @param Instance $instance user and network details for which the insight has to be generated
     * @param str $insight_date valid strtotime parameter for insight date, defaults to 'today'
     * @param bool $regenerate_existing_insight whether the insight should be regenerated over a day
     * @param str $day_of_year Day of the year to run in American MM/DD format. (ex. 12/25 = Christmas, December 25th)
     * @param int $count_related_posts if set, wouldn't run insight if there are no posts related to insight
     * @param arr $excluded_networks array of networks for which the insight shouldn't be run
     * @return bool Whether the insight should be generated or not
     */
    public function shouldGenerateAnnualInsight($slug, Instance $instance, $insight_date=null,
        $regenerate_existing_insight=false, $day_of_year=null, $count_related_posts=null, $excluded_networks=array()) {
        if (Utils::isTest()) {
            return true;
        }
        if ($day_of_year === null) {
            $day_of_year = date('n-j');
        }
        $run = self::shouldGenerateInsight($slug, $instance, $insight_date, $regenerate_existing_insight,
            $count_related_posts, $excluded_networks);
        if (!$run) {
            return false;
        }
        list($month, $date) = preg_split('/[^0-9]+/', $day_of_year);
        if ((int)$month == date('n') && (int)$date == date('j')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Determine whether an end-of-year insight should be generated.
     * This is similar to shouldGenerateAnnualInsight, with one key difference, in that it will return true for an
     * insight scheduled on a date that has passed. So if an insight should run on December 1st,
     * and a user joins ThinkUp on December 3rd, that insight should get generated.
     *
     * @param str $slug slug of the insight to be generated
     * @param Instance $instance user and network details for which the insight has to be generated
     * @param str $insight_date valid strtotime parameter for insight date, defaults $day_of_year in current year.
     * @param bool $regenerate_existing_insight whether the insight should be regenerated over a day
     * @param str $day_of_year Day of the year to run in American MM/DD format. (ex. 12/25 = Christmas, December 25th)
     * @param int $count_related_posts if set, wouldn't run insight if there are no posts related to insight
     * @param arr $excluded_networks array of networks for which the insight shouldn't be run
     * @return bool Whether the insight should be generated or not
     */
    public function shouldGenerateEndOfYearAnnualInsight($slug, Instance $instance, $insight_date=null,
        $regenerate_existing_insight=false, $day_of_year=null, $count_related_posts=null, $excluded_networks=array()) {
        if (Utils::isTest()) {
            return true;
        }
        $run = self::shouldGenerateInsight($slug, $instance, $insight_date, $regenerate_existing_insight,
            $count_related_posts, $excluded_networks);
        if (!$run) {
            return false;
        }
        if ($day_of_year === null) {
            $day_of_year = date('n-j');
        }
        //Do generate insight if today is past the end-of-year insight date
        $todays_day_of_year = date('z');
        list($month, $day) = preg_split('/[^0-9]+/', $day_of_year);
        $doy_date_str = (date('Y')).'-'.$month.'-'.$day ;
        $insight_day_of_year = date('z', strtotime($doy_date_str));
        return ($todays_day_of_year >= $insight_day_of_year);
    }

    /**
     * Take an array of string arrays, pick one at random and substitute each token with a value.
     * Text is processed with InsightTerms::getProcessedText()
     * The normal usage would be to pass an array of Insight fields, such as text, headline, etc.
     *
     * @param arr $copy_assoc_array Array of arrays. Key is a field name ('headline', 'text'), array is strings
     *                              representing possible copy choices for that field.
     * @param arr $substitutions Text replacement token/value pairs passed to getProccessedText(), in the form of
     *                           '%token'=>'value'. See also: InsightTerms::getProcessedText
     * @return arr The chosen and processed array
     */
    public function getVariableCopyArray($copy_assoc_array, $substitutions = array()) {
        $substitutions['username'] = $this->username;
        $choice = $copy_assoc_array[TimeHelper::getTime() % count($copy_assoc_array)];
        foreach ($choice as $key => $val) {
            $choice[$key] =  $this->terms->getProcessedText($choice[$key], $substitutions);
        }
        return $choice;
    }

    /**
     * Take an array of strings, pick one at random and substitute each token with a value.
     * Text is processed with InsightTerms::getProcessedText()
     * The normal usage would be to pass a list of string choices for an Insight field, such as text, headline, etc.
     *
     * @param array $copy_array Array of possible strings
     * @param array $substitutions Text replacement token/value pairs passed to getProccessedText(), in the form of
     *                            '%token'=>'value'. See also: InsightTerms::getProcessedText
     * @return str The chosen and processed array
     */
    public function getVariableCopy($copy_array, $substitutions = array()) {
        $substitutions['username'] = $this->username;
        $choice = $copy_array[TimeHelper::getTime() % count($copy_array)];
        return $this->terms->getProcessedText($choice, $substitutions);
    }

    public function renderConfiguration($owner) {
    }

    public function renderInstanceConfiguration($owner, $instance_username, $instance_network) {
    }

    public function activate() {
    }

    public function deactivate() {
    }
}
