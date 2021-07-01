<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\WebDav\LegacyConfiguration;

use Schoolbox\User;
use Schoolbox\YearLevel;

/**
 * Builder for legacy WebDAV configurations
 *
 * @deprecated This exists only so we don't break old configs on rollout of 17.
 *             This should be removed for 17.5.
 */
class Factory
{
    /**
     * Builds a legacy configuration.
     *
     * @param string $siteSchoolCode
     * @param User   $user    A user to retrieve configuration for
     * @param array  $session The current session
     * @return array
     */
    public static function build(string $siteSchoolCode, User $user, array $session): array
    {
        /** @var YearLevel\Model $year */
        $year     = $session['year'];
        $settings = [];
        $paths    = [];

        $siteSchoolCode = strtoupper(trim($siteSchoolCode));

        if ($siteSchoolCode === 'WSL') {
            $settings = array(
                'baseUri' => 'http://oxygen/',
                'userName' => "wgs/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    '/StaffHome/',
                    '/StaffData/',
                );
            } else {
                $settings['baseUri'] = 'http://mercury/';

                $paths = array(
                    '/StudentHome/' . (12 - (int)$year->getName() + date('y')) . '/' . $session['username'] . '/',
                    '/StudentData/',
                );
            }
        } elseif ($siteSchoolCode === 'jpc') {
            $settings = array(
                'baseUri' => 'https://malastare/',
                'userName' => "jpc/" . strtolower($session['username']),
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    '/Staff/' . strtolower($session['username']) . '/',
                    '/doclib/',
                );
            } else {
                $paths = array(
                    '/Students/' . (date('Y') - ((int)$year->getName() - 7)) . '/' . strtolower($session['username']) . '/',
                );
            }
        } elseif ($siteSchoolCode === 'TCS') {
            $settings = array(
                'baseUri' => 'http://senior-server.school.cathedral.qld.edu.au/',
                'userName' => "cathedral/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                if ($session['campus'][0] == 4 || $session['campus'][0] == 5) {
                    $settings['baseUri'] = 'http://junior-server.school.cathedral.qld.edu.au/';

                    $paths = array(
                        '/staffhome/' . $session['username'] . '/',
                        '/ydrive/',
                    );
                } else {
                    $paths = array(
                        '/staffhome/' . $session['username'] . '/',
                        '/xdrive/',
                    );
                }
            } else {
                $paths = array(
                    '/studenthome/' . $session['username'] . '/',
                );

                if ($session['campus'][0] == 4 || $session['campus'][0] == 5) {
                    $settings['baseUri'] = 'http://junior-server.school.cathedral.qld.edu.au/';
                }
            }
        } elseif ($siteSchoolCode === 'BC') {
            $settings = array(
                'baseUri' => 'https://webdav.bialik.vic.edu.au/',
                'userName' => "bialik/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                if ($session['campus'][0] == 2) {
                    $paths = array(
                        '/H-DRIVE/USERS/STAFF/TEACHERS/ELC/' . $session['username'] . '/',
                    );
                } else {
                    $paths = array(
                        '/H-DRIVE/USERS/STAFF/TEACHERS/YEAR02-12/' . $session['username'] . '/',
                    );
                }
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    '/H-DRIVE/USERS/STUDENTS/YEAR%20' . str_pad($year->getName(), 2, '0', STR_PAD_LEFT) . '/' . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'SMGS') {
            $settings = array(
                'baseUri' => 'https://drives.stmichaels.vic.edu.au/',
                'userName' => "smgs/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'staff/' . $session['username'] . '/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'SC') {
            $settings = array(
                'baseUri' => 'https://webdav.seymour.sa.edu.au/',
                'userName' => "sccampus/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'staffhome/' . ucwords($session['username']) . '/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . 'studenthome/' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'MGGS') {
            $settings = array(

                'userName' => 'mggs/' . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $settings['baseUri'] = 'http://mggs-webdav/';

                $paths = array(
                    '/STAFF/' . $session['username'] . '/',
                    '/EXCHANGE/',
                    '/morrishall/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $settings['baseUri'] = 'http://mggs-fs02/';
                $paths = array(
                    '/STUDENTS/' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'TS') {
            $settings = array(
                'userName' => 'tintern/' . $session['username'],
                'password' => $session['password'],
                'baseUri' => 'http://tnvmfs01/',
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    '/Staff/' . $session['username'] . '/'
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    '/Student/' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'SOC') {
            $settings = array(
                'baseUri' => 'http://davusers.soc.tas.edu.au/',
                'userName' => "soc/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                if ($session['campus'][0] == '1') {
                    $paths = array(
                        $settings['baseUri'] . 'penquitestaff/' . $session['username'] . '/Documents/',
                    );
                } else {
                    $paths = array(
                        $settings['baseUri'] . 'elphinstaff/' . $session['username'] . '/Documents/',
                    );
                }

                $fileShares = array(
                    'Academic',
                    'BIT',
                    'CommunityRelations',
                    'DramaAndDance',
                    'EdOutdoors',
                    'English',
                    'HPE',
                    'Humanities',
                    'LOTE',
                    'Maths',
                    'Music',
                    'PhotoStore',
                    'Science',
                    'Technology',
                    'VisualArts',
                    'RoundSquare',
                    'AdminDocs',
                    'AdminDocument'
                );

                foreach ($fileShares as $share) {
                    $paths[] = $settings['baseUri'] . '/' . $share . '/';
                }
            } elseif ($user->getRole()->isStudent()) {
                if ((int)$year->getName() >= '6') {
                    $paths = array(
                        $settings['baseUri'] . 'penquitestudents/Year12in' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/Documents/',
                    );
                } else {
                    $paths = array(
                        $settings['baseUri'] . 'elphinstudents/Year12in' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/Documents/',
                    );
                }
            }
        } elseif ($siteSchoolCode === 'IGGS') {
            $settings = array(
                'userName' => $session['username'],
                'password' => $session['password'],
                'baseUri' => 'https://myschool1.ivanhoegirls.vic.edu.au',
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . '/webdav/staff/' . $session['username'] . '/',
                    $settings['baseUri'] . '/webdav/shared/'
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . '/webdav/student/' . $session['username'] . '/',
                    $settings['baseUri'] . '/webdav/shared/'
                );
            }
        } elseif ($siteSchoolCode === 'SEDC') {
            $settings = array(
                'userName' => 'SEC/' . $session['username'],
                'password' => $session['password'],
                'baseUri' => 'http://myschoolfiles',
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . '/StaffHome/' . $session['username'] . '/',
                    $settings['baseUri'] . '/Assets/',
                    $settings['baseUri'] . '/StaffShares/',
                    $settings['baseUri'] . '/Submissions/'
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . '/StudentHome/' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/'
                );
            }
        } elseif ($siteSchoolCode === 'BTAC') {
            $settings = array(
                'userName' => $session['username'],
                'password' => $session['password'],
                'baseUri' => 'https://wd1.btac.nsw.edu.au',
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . '/home/' . $session['username'] . '/',
                    $settings['baseUri'] . '/vol/shared/',
                );
            }
        } elseif ($siteSchoolCode === 'FGS') {
            $settings = array(
                'baseUri' => 'https://files.fintona.vic.edu.au/',
                'userName' => "fintona/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'staff/',
                    $settings['baseUri'] . 'public/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . 'public/',
                );
            }
        } elseif ($siteSchoolCode === 'LC') {
            $settings = array(
                'baseUri' => 'http://lcapache.loyola.local:8080/',
                'userName' => "loyola/" . $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'Staff%20Home/' . $session['username'] . '/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . 'Student%20Home/' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'SHCL') {
            $settings = array(
                'baseUri' => 'http://svwebdav01.sheldoncollege.com/',
                'userName' => $session['username'] . "@sheldoncollege.com",
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'staffhome/' . $session['username'] . '/',
                    $settings['baseUri'] . 'collegeshare/',
                );
                if ($user->getRole()->getId() != '3' and $user->getRole()->getId() != '4') {
                    $paths = array_merge($paths, array(
                        $settings['baseUri'] . 'academicshare/',
                        $settings['baseUri'] . 'collegeshare/',
                        $settings['baseUri'] . 'photoshare/',
                        $settings['baseUri'] . 'studentshare/',
                    ));
                }
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . 'studenthome/' . $session['username'] . '/',
                    $settings['baseUri'] . 'studentshare/',
                );
            }
        } elseif ($siteSchoolCode === 'NGSL') {
            if ($user->getRole()->isStaff()) {
                $settings = array(
                    'baseUri' => 'http://facultycloud.ngs.nsw.edu.au/',
                    'userName' => $session['username'] . '@ngs.nsw.edu.au',
                    'password' => $session['password'],
                );
                $paths = array(
                    $settings['baseUri'] . 'KDrive/',
                    $settings['baseUri'] . 'staffhome/' . $session['username'] . '/',
                    $settings['baseUri'] . 'StudentShare/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $settings = array(
                    'baseUri' => 'http://studentcloud.ngs.nsw.edu.au/',
                    'userName' => $session['username'] . '@ngs.nsw.edu.au',
                    'password' => $session['password'],
                );
                $paths = array(
                    $settings['baseUri'] . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'MS') {
            $settings = array(
                'baseUri' => 'http://webdav.meriden.nsw.edu.au/',
                'userName' => "meriden/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'staff/' . $session['username'] . '/',
                    $settings['baseUri'] . 'staffgroup/',
                    $settings['baseUri'] . 'student/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'DSFG') {
            $settings = array(
                'baseUri' => 'http://rdrive.diocesan.school.nz/',
                'userName' => 'diont/' . $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = array($settings['baseUri'] . 'rdrive/',);
            }
        } elseif ($siteSchoolCode === 'TCCLL') {
            $settings = array(
                'baseUri' => 'https://files.trinitylismore.nsw.edu.au/',
                'userName' => "TRINITY/" . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . 'staffhome/' . $session['username'] . '/',
                    $settings['baseUri'] . 'staffshare/',
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    $settings['baseUri'] . 'studentshare/',
                );
            }
        } elseif ($siteSchoolCode === 'RLC') {
            $settings = array(
                'baseUri' => 'http://WebDavShareDrive.redeemer.net.au',
                'userName' => 'redeemer/' . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    'http://WebDavShareDrive.redeemer.net.au/',
                    'http://WebDavMediaDrive.redeemer.net.au/',
                    'http://WebDavHomeStaff.redeemer.net.au/' . $session['username'] . '/',
                    'http://WebDavHomeStudent.redeemer.net.au'
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    'http://WebDavShareDrive.redeemer.net.au',
                    'http://WebDavMediaDrive.redeemer.net.au',
                    'http://WebDavHomeStudent.redeemer.net.au/' . $session['username'] . '/'
                );
            }
        } elseif ($siteSchoolCode === 'accac') {
            $settings = array(
                'baseUri' => 'http://webdav.aquinas.qld.edu.au:8080',
                'userName' => $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = array(
                    '/'
                );
            } elseif ($user->getRole()->isStudent()) {
                $paths = array(
                    '/'
                );
            }
        } elseif ($siteSchoolCode === 'SLMP') {
            $settings = array(
                'baseUri' => 'https://sldm-wdav-01.stlouisaspendale.catholic.edu.au/',
                'userName' => 'stlouisdom/' . $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . '/staffshare/',
                    $settings['baseUri'] . '/reports/',
                    $settings['baseUri'] . '/thome/' . $session['username'] . '/',
                );
            }
            if ($user->getRole()->isParent()) {
                $paths = array(
                    $settings['baseUri'] . '/reports/' . $year->getName() . '/',
                );
            }
        } elseif ($siteSchoolCode === 'SICOL') {
            $settings = [
                'baseUri' => 'http://sbox_webdav.siena.vic.edu.au:81/',
                'userName' => 'siena/' . $session['username'],
                'password' => $session['password'],
            ];
            if ($user->getRole()->isStaff()) {
                $paths = [$settings['baseUri'] . 'StaffHome/' . $session['username'] . '/',];
            } elseif ($user->getRole()->isStudent()) {
                $paths = [$settings['baseUri'] . 'StudentHome/CL' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/',];
            }
        } elseif ($siteSchoolCode === 'DUCV') {
            $settings = array(
                'baseUri' => 'http://ducvfiles.dulum.vic.edu.au:8461',
                'userName' => $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = array(
                    $settings['baseUri'] . '/PublicDrive/',
                    $settings['baseUri'] . '/StaffHome/' . $session['username'] . '/',
                );
            }
        } elseif ($siteSchoolCode === 'SPGS') {
            $settings = array(
                'baseUri' => 'https://files.stpauls.nsw.edu.au/',
                'userName' => $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'StaffGroup/',
                    $settings['baseUri'] . 'StaffPublic/',
                    $settings['baseUri'] . 'StudentGroup/',
                    $settings['baseUri'] . 'StudentPublic/',
                ];
            } elseif ($user->getRole()->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'StudentGroup/',
                    $settings['baseUri'] . 'StudentPublic/',
                ];
            }
        } elseif ($siteSchoolCode === 'ALPGS') {
            $settings = array(
                'baseUri' => 'https://webdav.ags.vic.edu.au/',
                'userName' => 'ags\\' . $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'TDrive/',
                    $settings['baseUri'] . 'StaffHome/' . $session['username'] . '/',
                ];
            } elseif ($user->getRole()->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'StudentHome/' . $session['username'] . '/',
                    $settings['baseUri'] . 'StudentCommon/',
                ];
            }
        } elseif ($siteSchoolCode === 'SOHCS') {
            $settings = array(
                'baseUri' => 'http://10.0.0.1/shares/',
                'userName' => $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'central/',
                    $settings['baseUri'] . 'staff_data/' . $session['username'] . '/',
                ];
            } elseif ($user->getRole()->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'student_data/' . $session['username'] . '/',
                ];
            }
        } elseif ($siteSchoolCode === 'SCSB') {
            $settings = array(
                'baseUri' => 'https://scr-fs02.scr.vic.edu.au/',
                'userName' => 'SCR\\' . $session['username'],
                'password' => $session['password'],
            );
            if ($user->getRole()->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'staff/' . $session['username'] . '/',
                ];
            } elseif ($user->getRole()->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'students/year_12_of_' . (12 - (int)$year->getName() + date('Y')) . '/' . $session['username'] . '/',
                ];
            }
        } elseif ($siteSchoolCode === 'AGSBOT') {
            $settings = array(
                'baseUri' => 'http://webdav.ags.school.nz/',
                'userName' => 'AKGRAMMAR/' . $session['username'],
                'password' => $session['password'],
            );

            if ($user->getRole()->isStaff()) {
                $paths = [
                    $settings['baseUri'] . 'staff/' . $session['username'] . '/',
                    $settings['baseUri'] . 'departments/',
                    //                    $settings['baseUri'] . 'submission/',
                    $settings['baseUri'] . 'distribution/',
                ];
            } elseif ($user->getRole()->isStudent()) {
                $paths = [
                    $settings['baseUri'] . 'students/' . $session['username'] . '/',
                    //                    $settings['baseUri'] . 'submission/',
                    $settings['baseUri'] . 'distribution/',
                ];
            }
//        } elseif (in_array($siteLocation, array('core', 'demo'))) {
//            $settings = array(
//                'baseUri'  => 'http://127.0.0.1:8881/',
//                'userName' => $session['username'],
//                'password' => $session['password'],
//            );
//
//            $paths = ['/'];
        }

        return [
            'settings' => $settings,
            'paths'    => $paths,
        ];
    }
}
