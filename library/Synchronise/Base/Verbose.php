<?php
/**
 * Synchronise_Base_Verbose
 *
 * Standard verbosity reporting upon create/update/delete actions within Synchronise
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @stolen_from Steve Chan <steve.chan@alaress.com.au>
 */
class Synchronise_Base_Verbose {
	protected $verbose = false;
	protected $newUser = array();

	/* Stats
	 */
	protected $numCreated = 0;
	protected $numUpdated = 0;
	protected $numSkipped = 0;

    /**
     * A list of all the create and update actions that took place
     *
     * @var array
     */
    protected $allResults = array();

    /**
     * A list of all the skip actions
     *
     * @var array
     */
    protected $allSkipped = array();

    /**
     * Column widths for the icon/mode/action prefix for each row
     *
     * @var array
     */
    protected $prefixSpecification = array(
        'I' => 2,
        'Mode' => 12,
        'Action' => 15
    );

    /**
     * Column widths for the rest of the row which contains user data
     * These are the default lengths which get overridden if the data
     * exceeds their current values
     *
     * @var array
     */
    protected $rowSpecification = array(
        'Username' => 10,
        'Title' => 7,
        'Firstname' => 20,
        'Lastname' => 20,
        'SynergyID' => 12,
        'AltEmail' => 40,
        'Campus' => 10,
        'GroupID' => 9,
        'Year' => 5,
        'ePort.' => 8,
        'Webm.' => 7,
        'LocalEmail' => 12,
        'MailHandler' => 13
    );

    /**
	 * Create an intro
	 *
	 * @return void
	 */
	public function intro() {
		echo "This script automatically updates Schoolbox with user data found in your external database.\n\n";

		echo "There are two modes: 'Planning' and 'Performing'.\n\n";

		echo "Planning mode: activated using \$sync->setPerformUpdate(false);\n";
		echo "This allows you to see a preview of what is going to happen. No database changes are made.\n\n";

		echo "Performing mode: activated using \$sync->setPerformUpdate(true);\n";
		echo "This allows the script to modify the database.\n\n\n";

		echo "Legend ------------------------------------------------------\n\n";
		echo "+  Planning create: The script intends to create a Schoolbox account.\n";
		echo "^  Planning update: The script intends to update a Schoolbox account.\n\n";

		echo "+  Performing create: The script will create a Schoolbox account.\n";
		echo "^  Performing update: The script will update a Schoolbox account.\n\n";

		echo "o  Skipping create/update: The script intends to ignore this record altogether, regardless of mode.\n\n";
		echo "-------------------------------------------------------------\n";
		echo "\n\n";
	}

	/* These methods create verbosity strings which indicate what happened. You should override methods
	 * skip(), planCreation(), performCreation(), planUpdate(), performUpdate() and verboseUserSummary()
	 * in subclasses to make your own verbose strings.
	 */

    /**
     * Echo message about skipping all actions
     * Optionally pass in your own reason
     *
     * @param string $reason
     * @return void
     */
	public function skip($reason = null) {
		if ($this->getVerbose() === true) {

            /* Default reason
             */
            if ($reason === null) {
                $newUser = $this->getNewUser();

                $this->allSkipped[] = "o Skipping create/update of {$newUser['firstname']} {$newUser['lastname']} {$newUser['synergy_id']} because they have no username.";
            }
            /* Custom reason
             */
            else {
                $this->allSkipped[] = "o Skipping create/update {$reason}";
            }
        }
	}

	/**
	 * Echo message about planning creation
	 *
	 * @return void
	 */
	public function planCreation() {
		if ($this->getVerbose() === true) {
            $this->allResults[] = array_merge(
                array(
                    'I' => '+',
                    'Mode' => 'Planning',
                    'Action' => 'create'
                ),
                $this->verboseUserSummary($this->getNewUser())
            );
		}
	}

	/**
	 * Echo message about performing creation
	 *
	 * @return void
	 */
	public function performCreation() {
		if ($this->getVerbose() === true) {
            $this->allResults[] = array_merge(
                array(
                    'I' => '+',
                    'Mode' => 'Performing',
                    'Action' => 'create'
                ),
                $this->verboseUserSummary($this->getNewUser())
            );
		}
	}

	/**
	 * Echo message about planning update
	 *
	 * @return void
	 */
	public function planUpdate() {
		if ($this->getVerbose() === true) {
            $this->allResults[] = array_merge(
                array(
                    'I' => '^',
                    'Mode' => 'Planning',
                    'Action' => 'update'
                ),
                $this->verboseUserSummary($this->getNewUser())
            );
   		}
	}

	/**
	 * Echo message about performing update
	 *
	 * @return void
	 */
	public function performUpdate() {
		if ($this->getVerbose() === true) {
            $this->allResults[] = array_merge(
                array(
                    'I' => '^',
                    'Mode' => 'Performing',
                    'Action' => 'update'
                ),
                $this->verboseUserSummary($this->getNewUser())
            );
		}
	}

	/**
	 * Create a verbose summary string containing data about the
	 * current user being examined
	 *
	 * @param  $newUser
	 * @return string
	 */
	protected function verboseUserSummary($newUser) {
		if (is_array($newUser)) {
			$data = array();

            /* Username, title, firstname, lastname
             */
            $data['Username'] = $newUser['username'];
            $data['Title'] = $newUser['title'];
            $data['Firstname'] = $newUser['firstname'];
            $data['Lastname'] = $newUser['lastname'];

            /* Other fields
             */
			$data['SynergyID'] = $newUser['synergy_id'];
			$data['AltEmail'] = $newUser['alt_email'];
			$data['Campus'] = '[' . implode(',', $newUser['campus']) . ']';
			$data['GroupID'] = $newUser['group_id'];
            $data['Year'] = '';

			if (array_key_exists('year', $newUser)) {
				$data['Year'] = $newUser['year'];
			}

            /* Default settings
             */
            $data['ePort.'] = $newUser['eportfolio_enabled'];
            $data['Webm.'] = $newUser['webmail'];
            $data['LocalEmail'] = $newUser['local_email'];
            $data['MailHandler'] = $newUser['mail_handler'];

			return $data;
		}
	}

    /**
     * Find maximum lengths of columns, and adjust specification
     * to accommodate longest data
     *
     * sometimes numerous emails exist in alt email field
     * sometimes names are really long
     * arden campus value
     * etc
     */
    protected function adjustColumnWidths() {
        foreach ($this->rowSpecification as $title => &$padding) {
            foreach($this->getAllResults() as $currentRow) {
                $datalength = strlen($currentRow[$title]);

                if ($datalength > $padding) {
                    $padding = $datalength+2;
                }
            }
        }
    }

    /**
     * Format output row in verbose mode
     *
     * @param $row
     * @internal param $prefix
     * @internal param $data
     * @return mixed
     */
    private function formatRow($row) {
        $formattedRow = $row;

        foreach ($this->getPrefixSpecification() as $title => $padding) {
            $formattedRow[$title] = str_pad($row[$title], $padding);
        }

        foreach ($this->getRowSpecification() as $title => $padding) {
            $formattedRow[$title] = str_pad($row[$title], $padding);
        }

        return implode('', $formattedRow);
    }

    /**
     * Echo headings
     */
    protected function dumpHeadings() {
        /* Formatted output
         */
        $heading = array();

        /* Prefix column widths
         */
        foreach($this->getPrefixSpecification() as $title => $padding) {
            $heading[$title] = str_pad($title, $padding);
        }

        /* Data column widths
         */
        foreach($this->getRowSpecification() as $title => $padding) {
            $heading[$title] = str_pad($title, $padding);
        }

        echo implode('', $heading);
        echo "\n\n";
    }

    /**
     * Echo create/update results
     */
    protected function dumpResults() {
        foreach ($this->getAllResults() as $currentRow) {
            echo $this->formatRow($currentRow);
            echo "\n";
        }

        echo "\n\n";
    }

    /**
     * Echo skipped results
     */
    protected function dumpSkipped() {
        foreach ($this->getAllSkipped() as $currentRow) {
            echo $currentRow . "\n";
        }

        echo "\n\n";
    }

    /**
	 * A summary count of changes
	 *
	 * @return void
	 */
	public function summary() {
        $this->adjustColumnWidths();
        $this->dumpHeadings();
        $this->dumpResults();
        $this->dumpSkipped();

		echo "Created: {$this->getNumCreated()}, ";
		echo "Updated: {$this->getNumUpdated()}, ";
		echo "Skipped: {$this->getNumSkipped()}";
		echo "\n\n";
	}

	/* Getters/Setters
	 */

	/**
	 * Increment the number of items created
	 *
	 * @return void
	 */
	public function incrementCreated() {
		$this->numCreated++;
	}

	/**
	 * Increment the number of items updated
	 *
	 * @return void
	 */
	public function incrementUpdated() {
		$this->numUpdated++;
	}

	/**
	 * Increment the number of items skipped
	 *
	 * @return void
	 */
	public function incrementSkipped() {
		$this->numSkipped++;
	}

	/**
	 * Get number of items created
	 *
	 * @return int
	 */
	public function getNumCreated() {
		return $this->numCreated;
	}

	/**
	 * Get number of items updated
	 *
	 * @return int
	 */
	public function getNumUpdated() {
		return $this->numUpdated;
	}

	/**
	 * Get number of items skipped
	 *
	 * @return int
	 */
	public function getNumSkipped() {
		return $this->numSkipped;
	}

	/**
	 * Set Verbose estting
	 *
	 * @param  $val
	 * @return void
	 */
	public function setVerbose($val) {
		$this->verbose = $val;
	}

	/**
	 * Get Verbose setting
	 *
	 * @return bool
	 */
	public function getVerbose() {
		return $this->verbose;
	}

	/**
	 * Set new user
	 *
	 * @param  $val
	 * @return void
	 */
	public function setNewUser($val) {
		$this->newUser = $val;
	}

	/**
	 * Get new user
	 *
	 * @return array
	 */
	protected function getNewUser() {
		return $this->newUser;
	}

    /**
     * Set all results
     *
     * @param array $allResults
     */
    protected function setAllResults($allResults)
    {
        $this->allResults = $allResults;
    }

    /**
     * Get all results
     *
     * @return array
     */
    protected function getAllResults()
    {
        return $this->allResults;
    }

    /**
     * Set all skipped
     *
     * @param array $allSkipped
     */
    protected function setAllSkipped($allSkipped)
    {
        $this->allSkipped = $allSkipped;
    }

    /**
     * Get all skipped
     *
     * @return array
     */
    protected function getAllSkipped()
    {
        return $this->allSkipped;
    }

    /**
     * Set prefix specification
     *
     * @param array $prefixSpecification
     */
    protected function setPrefixSpecification($prefixSpecification)
    {
        $this->prefixSpecification = $prefixSpecification;
    }

    /**
     * Get prefix specification
     *
     * @return array
     */
    protected function getPrefixSpecification()
    {
        return $this->prefixSpecification;
    }

    /**
     * Set row specification
     *
     * @param array $rowSpecification
     */
    protected function setRowSpecification($rowSpecification)
    {
        $this->rowSpecification = $rowSpecification;
    }

    /**
     * Get row specification
     *
     * @return array
     */
    protected function getRowSpecification()
    {
        return $this->rowSpecification;
    }
}
