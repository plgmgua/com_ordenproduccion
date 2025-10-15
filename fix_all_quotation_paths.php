<?php
/**
 * Fix All Quotation Paths Script
 * 
 * This script fixes all quotation_files paths in the database:
 * 1. Converts Google Drive URLs to correct local path format
 * 2. Fixes incorrectly formatted local paths
 * 3. Validates all changes
 * 
 * Usage: php fix_all_quotation_paths.php
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Fix
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

// Set time limit for long operations
set_time_limit(0);
ini_set('memory_limit', '512M');

// --- CONFIGURATION ---
$tableName = 'joomla_ordenproduccion_ordenes';

// Database configuration
$db_host = 'localhost';
$db_user = 'joomla';
$db_pass = 'Blob-Repair-Commodore6';
$db_name = 'grimpsa_prod';

// Log file
$logFile = __DIR__ . '/fix_all_quotation_paths.log';

// Colors for output
$colors = [
    'red' => "\033[0;31m",
    'green' => "\033[0;32m",
    'yellow' => "\033[1;33m",
    'blue' => "\033[0;34m",
    'purple' => "\033[0;35m",
    'cyan' => "\033[0;36m",
    'nc' => "\033[0m" // No Color
];

// Google Drive URL mappings provided by user
$googleDriveMappings = [
    '01003' => 'https://drive.google.com/open?id=1RMtoRy5GfJSQD96rAbm1tM1FEEa9SL7l',
    '01004' => 'https://drive.google.com/open?id=1dBwAku1SdsQdTyAMqpBxk2s-hzG0skCe',
    '00105' => 'https://drive.google.com/open?id=1sOt70K2rDemjNcQt1ZDE-IozpWVgAdrp',
    '01118' => 'https://drive.google.com/open?id=1yE9sMNKHxNLoJSZ_PUnDwboJ3qP3Te0I',
    '00116' => 'https://drive.google.com/open?id=13clfJOtUF8OUMBMLbipS0AQbojfN7KHW',
    '00126' => 'https://drive.google.com/open?id=1SDeW1iQyOeypaBP86hiksCeeh_tqXgVr',
    '01306' => 'https://drive.google.com/open?id=1VKMNYe1UqcCE7j6VvagtbzH0Zu03k7fJ',
    '01307' => 'https://drive.google.com/open?id=18jLPGQf-eM0nHs2G-VU036ksNUoABfWZ',
    '01309' => 'https://drive.google.com/open?id=16Ms3CO4d36dpFPd6SlzeFDMbaZqAlgrf',
    '01336' => 'https://drive.google.com/open?id=1AZ_KZC-WNPnlPGwr3egDb9c8MBQPBZB1',
    '01348' => 'https://drive.google.com/open?id=1rzsmtpzlnvxT66P1jRC0L-TQjY5v7EAI',
    '01361' => 'https://drive.google.com/open?id=1rhkbJ5P9cNfpcC-xLBtjzDebekLqv8Kt',
    '01381' => 'https://drive.google.com/open?id=12JrlrvZDxRdLGNMFnP2lzA_bX1UZCJr5',
    '01427' => 'https://drive.google.com/open?id=1Fhro5xipf6OwAwN1sxBfkILZ7eRgaNPR',
    '01429' => 'https://drive.google.com/open?id=1652Ujp-XBwTtP3BSqOXEjF4e-Pj4KCTY',
    '01478' => 'https://drive.google.com/open?id=1OzfaXY1lHgG3UbmLB6I0y_aeQcHOqzF1',
    '01531' => 'https://drive.google.com/open?id=1p8vdj5TuWfY_0vIn_SBLSVBqDiP-H7V_',
    '01541' => 'https://drive.google.com/open?id=10x0_swxEPVuoHE9wIoKSKsMPyCgqhVP7',
    '01570' => 'https://drive.google.com/open?id=14baovmPXwWaUcOe_k_eeZ69UQBSO_g3l',
    '01584' => 'https://drive.google.com/open?id=11vrFxggYmfqOzWKT5R1zZedvRxmraNHA',
    '01587' => 'https://drive.google.com/open?id=1QsGdFC_5dJGRNfxCh2ZlWbqGccAfrYnO',
    '01588' => 'https://drive.google.com/open?id=10CiAD0O6juTz3Pp2ig7ej8HlA6G9HJtX',
    '01593' => 'https://drive.google.com/open?id=1c5q7-RwGsFlup5lcgqmFAMptSo5hunX2',
    '01594' => 'https://drive.google.com/open?id=1XGmJtNLAGtja4i-cIfkyKAUPvbpOiyUQ',
    '01595' => 'https://drive.google.com/open?id=1_VTuNqrsWR8RtaTf2I6j2WMPB9wudksM',
    '01596' => 'https://drive.google.com/open?id=1-TC0rSPTw5dR2rrw3cnNhS0IrjXs2QrR',
    '01600' => 'https://drive.google.com/open?id=1UyM_TgqbH39MkIT87HVTTnfYNu6edfJn',
    '01625' => 'https://drive.google.com/open?id=17BcLkpIm1HFS6-mqH-ECNetZvnvUYd-7',
    '01701' => 'https://drive.google.com/open?id=1qm5E_bVg_KIe8dvJ9Ymlghs2bPR-2ulS',
    '01707' => 'https://drive.google.com/open?id=1dyQYDJgyPZT6-DWtOicX0nFftV_kSvjt',
    '01708' => 'https://drive.google.com/open?id=1JOoBwPd268GI2dfqgVP2I1cSSqPl4bwu',
    '01716' => 'https://drive.google.com/open?id=1hQF1Ca-FUrrwedN-cbKD9XPdKQvIf8rd',
    '01724' => 'https://drive.google.com/open?id=1jXqT1iI3ZPtqRGwp2y4sxeAA7o9b5g5c',
    '01737' => 'https://drive.google.com/open?id=1D8mOGvI-R90VS_SZHBATlgxnajmyPnl5',
    '01758' => 'https://drive.google.com/open?id=11Fsynq1Yoza_1nJe6gVd2w5-RqQx9wGX',
    '01769' => 'https://drive.google.com/open?id=1pg3ewAHElCnG_zxo-StTwhABELtGY1-O',
    '01771' => 'https://drive.google.com/open?id=1AlCMpvrY1SAJvJuMpUI2CAj5S5kLlpIz',
    '01781' => 'https://drive.google.com/open?id=11md-oHOoM_2X7KcLd15j5n9lyLYHQUc7',
    '01786' => 'https://drive.google.com/open?id=1W-SRwXB2Ss9dV3RZpPYjqrU8Kgu4a0ND',
    '01791' => 'https://drive.google.com/open?id=1vUBuLUKlQqUkySIRuiPcrfpifYEvvul5',
    '01799' => 'https://drive.google.com/open?id=1KE8hWW7GHEZfxyadyBDxpc3apWT0izJL',
    '01833' => 'https://drive.google.com/open?id=1SG0qqhQMBH_SdHdNZDkm65kui9mjXyM0',
    '01852' => 'https://drive.google.com/open?id=1D010fsjSDN1MFGYGDHUyIFuMCjXrLzMi',
    '01862' => 'https://drive.google.com/open?id=1gnqrezVd2yfOi0hPN1pIo1ECYrXdQGRu',
    '01867' => 'https://drive.google.com/open?id=1VbCofjcLZ2-8gNs2-a1G09Uitlkdr8ro',
    '01881' => 'https://drive.google.com/open?id=18fM3c04wR9TrQER74Z7BigGRUPNC4gLs',
    '01884' => 'https://drive.google.com/open?id=1U2gpvr_9lOSzJ4rRXQODC2noOwWYGc71',
    '01887' => 'https://drive.google.com/open?id=12GOrylF_8qnHv6UvV52THq2tcowKasn-',
    '01899' => 'https://drive.google.com/open?id=1h1h02V9qdxURToIXqFJPuv074klQEeXm',
    '01901' => 'https://drive.google.com/open?id=1iewhfjp6-REymTbvcPuz7s6D0kjkfknd',
    '01914' => 'https://drive.google.com/open?id=1pRr0CDtIspBwA8Yr3bwoGHyX6feFW7ge',
    '01919' => 'https://drive.google.com/open?id=1_X5wpXnhp2--c1R63xXTyIz0tchQ-ugS',
    '01937' => 'https://drive.google.com/open?id=164xkwg6JzWIFUVBA8EuLNhtNdFuE4emO',
    '01941' => 'https://drive.google.com/open?id=12dFjN1HZi-_s5rKLx497z-6QskGwSTgu',
    '01952' => 'https://drive.google.com/open?id=16ZMt94AU7x-xmI7eaTYijxIoEio3mOED',
    '01968' => 'https://drive.google.com/open?id=1r4Cu1fTFaLg4RR-upaCDDhP7V1nkFjps',
    '01970' => 'https://drive.google.com/open?id=1i5hhL9V43pcVlBmvLum4H2a0ViuQaA0n',
    '01976' => 'https://drive.google.com/open?id=1csnqHdiMy9oYVmhT99pSv1A29s176ucU',
    '01977' => 'https://drive.google.com/open?id=1G5xGyjgONLBdovL_rssVQ0mQ2IPBTfPc',
    '01983' => 'https://drive.google.com/open?id=1Hy8pKxQrLwT0aXKPVVtDzrvV8DsJl1Z2',
    '01995' => 'https://drive.google.com/open?id=1kjEEoMCQNsE066fPeBmgZS44plEqj0U1',
    '01996' => 'https://drive.google.com/open?id=1VWczjGW5rKuU8LYHy9eK0qQiDDL2vFu0',
    '02051' => 'https://drive.google.com/open?id=1ordauLjPLLPS4__LHbOUQRehaanlUJkd',
    '02060' => 'https://drive.google.com/open?id=1ojBMrB2fRjNYd3OCFSqmItbrEpj4piuX',
    '02063' => 'https://drive.google.com/open?id=1u1r2vUCUJK_Y7WCmI0a9KDmO1ga3qWCr',
    '02064' => 'https://drive.google.com/open?id=1Up2Yev16SC_NLcrAMdmhs9bQpWVARORD',
    '02065' => 'https://drive.google.com/open?id=12EaBwC_vwugDlSpy1SvPTk2uUwoNSOBs',
    '02066' => 'https://drive.google.com/open?id=1jq9v_wv1PPfqibrwskUBgZyL8LUqMJhX',
    '02067' => 'https://drive.google.com/open?id=1lMy01a5VeRZZkGt-KP5Bm8CfDwZN68-b',
    '02068' => 'https://drive.google.com/open?id=1OiNgvkaZSeH28Ys6JC7GJiFz_QySnvQz',
    '02069' => 'https://drive.google.com/open?id=1Dcfct0Fe9Ilo_4PduH9d-sz4gADuBL2G',
    '02070' => 'https://drive.google.com/open?id=1oKCRMWInKqV4Nh-lij9glV3jpro_PYQg',
    '02076' => 'https://drive.google.com/open?id=1tTGGPSqrVCBhopYoJW9SeF9mJOv2-hE9',
    '00208' => 'https://drive.google.com/open?id=1MOuf9oBUTO5ls9SaMbtbb9LP5Smkaw9Y',
    '02084' => 'https://drive.google.com/open?id=1BoWa0XOUr0LqO7mDgZHv9UUbkHbS_1gK',
    '02115' => 'https://drive.google.com/open?id=1Rwnx64sFmPcnJ_CJBoZqUpgV57lRYEG_',
    '02121' => 'https://drive.google.com/open?id=1j_UUEp5o2HRGupxxgNfvDXpazx-6_95T',
    '02124' => 'https://drive.google.com/open?id=1VF03PqAadi3s2Z4YSdF7vJ5r3CrP-b1E',
    '02125' => 'https://drive.google.com/open?id=14Zybh0j8NA8Bjeqx9lVaoZyW-4PIdWxC',
    '02129' => 'https://drive.google.com/open?id=1YKt5WdcfzDob51L25OETN1iVFvM4B0un',
    '02145' => 'https://drive.google.com/open?id=1ZAaT-AKXZwa8fPqHMmjc-0M0bIgwM5p1',
    '02153' => 'https://drive.google.com/open?id=1jA4F514wbRfmDBF6A1l8PRSD1QH3cPGo',
    '02155' => 'https://drive.google.com/open?id=1gggFQ5TBHHRaZYbCtnYtXgTS6CXabuVj',
    '02165' => 'https://drive.google.com/open?id=1qb1pg2TvidShhlSr0DSCNClkLJBUixHk',
    '02177' => 'https://drive.google.com/open?id=1FEeSW8W-_8DMUbw3YyyElrqs4iBg4PlF',
    '02178' => 'https://drive.google.com/open?id=1LJw4CsXKa88f3DGexqWnuOX3B3AxS9mB',
    '02182' => 'https://drive.google.com/open?id=1o-YcptBo19Ah4VBwsqX5G4vo-bs6Hgf7',
    '02235' => 'https://drive.google.com/open?id=1KohGWlv70H1hU4jUcjgRy1-fC2p3tXjP',
    '00224' => 'https://drive.google.com/open?id=1VAEXF7c_2KxWoBa-_qfKR4_k2zNXacFT',
    '02280' => 'https://drive.google.com/open?id=15vJ5Lyp4aQ6LAVcH60j5ijE7MYxC0LWY',
    '02296' => 'https://drive.google.com/open?id=1WaGAukFBbebxqmnv9CfTi2aLpBHL_pID',
    '02387' => 'https://drive.google.com/open?id=1yh3ieHlqlsNfLZ4pA_OBFSS_NGY9O_Dw',
    '02484' => 'https://drive.google.com/open?id=1ol9ioZV1msbl6HvuCp8lhotFp7nTyJj7',
    '02518' => 'https://drive.google.com/open?id=11RMqEgVpeo52cGAh8fTosUcxLbX3FioE',
    '02538' => 'https://drive.google.com/open?id=1b-KaOwJda0rId4Wbw4qQvYHqXULKE8VC',
    '02555' => 'https://drive.google.com/open?id=1sRim3V3zzyEVAMaxxRLTJjUenOdU0YBc',
    '02595' => 'https://drive.google.com/open?id=1RTIHgKFA_9RyCl2gUbk2Z8D90dgfjPwM',
    '02612' => 'https://drive.google.com/open?id=1IS5MPTnV2ku3UCah4TxaksJI1BNrFvCu',
    '02619' => 'https://drive.google.com/open?id=1wzaq1sddcvl7aqWPJjsf9jBMRqRG9E2Z',
    '02620' => 'https://drive.google.com/open?id=1JbUAY_wiTIKkjMnBm0aBeuG7uJEJqY0m',
    '02677' => 'https://drive.google.com/open?id=1gG66QzLZhltuAYCPrIXIvga9LT72_eo2',
    '00268' => 'https://drive.google.com/open?id=1Xvuif2j3VYHmlD0c9ks8sZKtkN6xOZB5',
    '02692' => 'https://drive.google.com/open?id=19sPakblPP8f_np8vMYdTRDREJ1ltH8wy',
    '02693' => 'https://drive.google.com/open?id=1bIy8-jtqvq7CCOO3tE_pdPkPKIchHDVy',
    '02694' => 'https://drive.google.com/open?id=16ewPgAWJpyW5drGqU5JmdZhXZtZReEma',
    '02720' => 'https://drive.google.com/open?id=1EVvKAXXa7JFKYazb0EbpCgRTnZmoUqoQ',
    '02787' => 'https://drive.google.com/open?id=1wtKfjNJiZqYDXfSccmNrKUn9RIXfv6Cb',
    '02798' => 'https://drive.google.com/open?id=1zyXI0fzXGcexzzEq8JGLDWL_akF31jUx',
    '02865' => 'https://drive.google.com/open?id=1m38zyoYNWy1-_2Te9w6qJFv9_K7Sv0Ow',
    '02900' => 'https://drive.google.com/open?id=18-pvbPNb_0xCc-lDM7Pa9RGrT2KjJWXU',
    '02953' => 'https://drive.google.com/open?id=1JsY5fqcnqR-9eFr3Yncx9_yI1g3Th0Mk',
    '03008' => 'https://drive.google.com/open?id=1eQQMCuHer7e10mqSaTDkjyCCKtV8sMaI',
    '03037' => 'https://drive.google.com/open?id=1CcUIUalD8kuvyJhhB-L_YRc3it2MMZN3',
    '03065' => 'https://drive.google.com/open?id=1B3_8kypUH9JQJR_xl2OAqhxVGmc43f--',
    '03072' => 'https://drive.google.com/open?id=1-XLRgvkhgIv-zue7Z-kQZ4Gv_8lBC2DQ',
    '03074' => 'https://drive.google.com/open?id=1J3kwv39eegcvksgSU3sLfDtId5sJQy2d',
    '03172' => 'https://drive.google.com/open?id=1svhsf25WL-_SEHhC_b_1bOLzUJunOKq-',
    '03262' => 'https://drive.google.com/open?id=153Lpn4cl1sAAhLzfxiKarixXKCfhvh26',
    '03275' => 'https://drive.google.com/open?id=1n2yBOwz79qIrKlY1fAbrW6d37RB2ngB9',
    '03292' => 'https://drive.google.com/open?id=192gKqSOnYRm_y5_pxZuU_2-XPQIa8-E8',
    '03334' => 'https://drive.google.com/open?id=1kMpvlW3njUzRGK6kFYldVUBQMzCrwy3-',
    '03341' => 'https://drive.google.com/open?id=1rttnq0fYcOgixNZUdyv-uMbZPRx6_IvS',
    '03364' => 'https://drive.google.com/open?id=1G_10oqxwRUXKb289ffwfdQu4940x-_g7',
    '03367' => 'https://drive.google.com/open?id=1SMcb9KZPFXbggLtu_CM3ZSR1Fc3h9cNB',
    '03370' => 'https://drive.google.com/open?id=1KzZM2HqQl20UgqO1PgsvZtYv2n9WMzTU',
    '03398' => 'https://drive.google.com/open?id=1taY8J6HgRjRBQzRgg1b8bWmexLA5JJxC',
    '03415' => 'https://drive.google.com/open?id=1P_BQ6h3A47m72kiIIb0yNMLSNtX7gy1N',
    '03439' => 'https://drive.google.com/open?id=1g6c8VXm0Lzc3iFXbPhSmYQnQROXsuviU',
    '03442' => 'https://drive.google.com/open?id=1afg090wyCW7JCXvpBTsuRqOm7eRNH7YF',
    '03465' => 'https://drive.google.com/open?id=1oELll6G6MIeaVnyt82JtP7jPaXdcPb8y',
    '03480' => 'https://drive.google.com/open?id=1UrVtTWYLN6NZIzRzjUb96ZNWF55a108f',
    '00355' => 'https://drive.google.com/open?id=1pMW1ExoW0KYxA94MNmbLgQsxLpuBWrV0',
    '00356' => 'https://drive.google.com/open?id=1rE9vQTyGZxivnNAZCffRf77NuDeK6sJ2',
    '00358' => 'https://drive.google.com/open?id=1u8FEx2wKB9DCU_V8swCsxkz9Iko2pt5X',
    '00359' => 'https://drive.google.com/open?id=18L44p5j7w-r6yIX-rftAfvDDwz9TFasV',
    '00364' => 'https://drive.google.com/open?id=1OfC0onvSmNd_KfC2vcfa1ysAhTEF0lIT',
    '00368' => 'https://drive.google.com/open?id=1j-blz-tS3k78Ta7Y9LiyByslItrzlVb4',
    '00446' => 'https://drive.google.com/open?id=1KewtPVcpc6Dl544KGeeFfj7JeRbCLkaT',
    '00454' => 'https://drive.google.com/open?id=1gpMf5a7mayx6V1BXGlunh-n9GHOKeaik',
    '00465' => 'https://drive.google.com/open?id=1ao95QM9wIQxhtKOpkzZGLg5nFCNVfdii',
    '00608' => 'https://drive.google.com/open?id=10K5_KW-DJfDpuQ-dClyRqzQD4-ft-aLn',
    '00728' => 'https://drive.google.com/open?id=1q2Rv_8cuB4kc4XP2efwFvwXQUByftYoj',
    '00802' => 'https://drive.google.com/open?id=1Uy-Qd8jCkdLZWX_0KPEyR_KJik4OwRCd',
    '00991' => 'https://drive.google.com/open?id=1xtKIDli11Yu5v_FsLATALxND7AjrbPqs'
];

// Logging functions
function logMessage($message, $type = 'info') {
    global $logFile, $colors;
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";
    
    // Console output with colors
    switch ($type) {
        case 'error':
            echo $colors['red'] . "[ERROR] " . $colors['nc'] . $message . "\n";
            break;
        case 'success':
            echo $colors['green'] . "[SUCCESS] " . $colors['nc'] . $message . "\n";
            break;
        case 'warning':
            echo $colors['yellow'] . "[WARNING] " . $colors['nc'] . $message . "\n";
            break;
        case 'info':
            echo $colors['blue'] . "[INFO] " . $colors['nc'] . $message . "\n";
            break;
        case 'data':
            echo $colors['cyan'] . "[DATA] " . $colors['nc'] . $message . "\n";
            break;
        case 'category':
            echo $colors['purple'] . "[CATEGORY] " . $colors['nc'] . $message . "\n";
            break;
    }
    
    // Write to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

echo "==========================================\n";
echo "  Fix All Quotation Paths Script\n";
echo "  Version: 1.0.0\n";
echo "==========================================\n\n";

logMessage("Starting comprehensive quotation paths fix process at: " . date('Y-m-d H:i:s'));
logMessage("Memory limit: " . ini_get('memory_limit'));
logMessage("Time limit: " . ini_get('max_execution_time') . " seconds");

// --- ANALYSIS FUNCTIONS ---

function isCorrectFormat($url) {
    if (empty($url)) {
        return false;
    }
    
    // Check if it's a plain string path starting with media/
    // We want format: 'media/com_ordenproduccion/cotizaciones/...'
    return strpos($url, 'media/') === 0 && strpos($url, 'com_ordenproduccion') !== false;
}

function isGoogleDriveUrl($url) {
    // Check if it's a plain Google Drive URL
    if (strpos($url, 'drive.google.com') !== false) {
        return true;
    }
    
    // Check if it's a JSON array containing a Google Drive URL
    $decoded = json_decode($url, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded[0])) {
        return strpos($decoded[0], 'drive.google.com') !== false;
    }
    
    return false;
}

function isLocalPath($url) {
    return strpos($url, 'media/') === 0 || strpos($url, '/media/') === 0;
}

function formatUrlCorrectly($localPath) {
    // Remove leading slash if present for consistency
    $cleanPath = ltrim($localPath, '/');
    
    // Ensure the path starts with media/ (not /media/)
    if (strpos($cleanPath, 'media/') !== 0) {
        $cleanPath = 'media/' . $cleanPath;
    }
    
    // Return plain string format: 'media/com_ordenproduccion/cotizaciones/...'
    return $cleanPath;
}

function getOrderNumberFromOrden($ordenDeTrabajo) {
    // Extract number from ORD-000001 format
    if (preg_match('/ORD-(\d+)/', $ordenDeTrabajo, $matches)) {
        return $matches[1];
    }
    return null;
}

function getLocalPathForOrder($ordenDeTrabajo, $createdDate) {
    // Convert ORD-000000 to COT-000000
    $cotNumber = str_replace('ORD-', 'COT-', $ordenDeTrabajo);
    
    // Determine year/month folder based on created date
    $year = date('Y', strtotime($createdDate));
    $month = date('m', strtotime($createdDate));
    
    // Return the local path in plain string format (no leading slash)
    return "media/com_ordenproduccion/cotizaciones/$year/$month/$cotNumber.pdf";
}

function extractDriveFileId($url) {
    // Handle both plain URLs and JSON arrays
    $urls = [];
    
    // Try to decode as JSON first
    $decoded = json_decode($url, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $urls = $decoded;
    } else {
        // Treat as plain URL
        $urls = [$url];
    }
    
    foreach ($urls as $singleUrl) {
        // Extract Google Drive file ID from various URL formats
        if (preg_match('/\/d\/([-\w]{25,})/', $singleUrl, $matches)) {
            return $matches[1];
        }
        if (preg_match('/id=([-\w]{25,})/', $singleUrl, $matches)) {
            return $matches[1];
        }
        if (preg_match('/([-\w]{25,})/', $singleUrl, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

function downloadGoogleDriveFile($fileId, $localPath) {
    $downloadUrl = "https://drive.google.com/uc?export=download&id=" . $fileId;
    
    // Create directory if it doesn't exist
    $dir = dirname($localPath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }
    
    // Download using cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    
    $fileContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($fileContent === false || !empty($error) || $httpCode != 200) {
        return false;
    }
    
    // Save file
    if (file_put_contents($localPath, $fileContent) === false) {
        return false;
    }
    
    // Verify file was saved correctly
    return file_exists($localPath) && filesize($localPath) > 0;
}

try {
    // --- MYSQL CONNECTION ---
    logMessage("Connecting to database...");
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($mysqli->connect_error) {
        throw new Exception('MySQL connection failed: ' . $mysqli->connect_error);
    }
    logMessage("✅ Database connection successful", 'success');

    // --- PROCESS ALL RECORDS INDIVIDUALLY ---
    logMessage("Reading and analyzing all records with quotation_files...");
    
    // Get all records with quotation_files
    $query = "SELECT id, orden_de_trabajo, quotation_files, created FROM $tableName WHERE quotation_files IS NOT NULL AND quotation_files != '' ORDER BY id";
    $result = $mysqli->query($query);

    if (!$result) {
        throw new Exception("Query failed: " . $mysqli->error);
    }

    $totalRecords = $result->num_rows;
    logMessage("Found $totalRecords records with quotation_files to analyze");

    $processedCount = 0;
    $googleDriveFixed = 0;
    $localPathFixed = 0;
    $alreadyCorrect = 0;
    $errors = 0;
    $skipped = 0;

    // Process each record individually
    while ($row = $result->fetch_assoc()) {
        $processedCount++;
        $recordId = $row['id'];
        $ordenDeTrabajo = $row['orden_de_trabajo'];
        $quotationFiles = $row['quotation_files'];
        $createdDate = $row['created'];
        
        logMessage("Processing record $processedCount/$totalRecords: $ordenDeTrabajo (ID: $recordId)");
        logMessage("Current path: " . substr($quotationFiles, 0, 100) . (strlen($quotationFiles) > 100 ? '...' : ''));

        // Analyze the current path format
        if (isCorrectFormat($quotationFiles)) {
            logMessage("✅ Already in correct format - skipping", 'warning');
            $alreadyCorrect++;
            continue;
        }

        // Check if it's a Google Drive URL that needs conversion
        if (isGoogleDriveUrl($quotationFiles)) {
            $orderNum = getOrderNumberFromOrden($ordenDeTrabajo);
            
            // Check if we have a mapping for this order OR try to download directly
            $fileId = extractDriveFileId($quotationFiles);
            
            if ($fileId) {
                logMessage("Processing Google Drive URL with file ID: $fileId");
                
                // Generate the correct local path based on created date
                $localPath = getLocalPathForOrder($ordenDeTrabajo, $createdDate);
                $absoluteLocalPath = '/var/www/grimpsa_webserver/' . $localPath;
                
                // Check if file already exists locally
                if (file_exists($absoluteLocalPath)) {
                    logMessage("File already exists locally, just updating database path");
                } else {
                    logMessage("Downloading file from Google Drive...");
                    if (!downloadGoogleDriveFile($fileId, $absoluteLocalPath)) {
                        logMessage("❌ Failed to download file for $ordenDeTrabajo", 'error');
                        $errors++;
                        continue;
                    }
                    logMessage("✅ Successfully downloaded file to: $absoluteLocalPath");
                }
                
                // Update database with local path
                $formattedPath = formatUrlCorrectly($localPath);
                logMessage("New path: $formattedPath");
                
                $stmt = $mysqli->prepare("UPDATE $tableName SET quotation_files = ? WHERE id = ?");
                $stmt->bind_param('si', $formattedPath, $recordId);
                
                if ($stmt->execute()) {
                    logMessage("✅ Successfully converted Google Drive URL for $ordenDeTrabajo (ID: $recordId)", 'success');
                    $googleDriveFixed++;
                } else {
                    logMessage("❌ Failed to update $ordenDeTrabajo: " . $stmt->error, 'error');
                    $errors++;
                }
                $stmt->close();
            } else {
                logMessage("⚠️ Could not extract file ID from Google Drive URL - skipping", 'warning');
                $skipped++;
            }
        }
        // Check if it's a local path that needs formatting OR if it's JSON format that should be plain string
        elseif (isLocalPath($quotationFiles) || (strpos($quotationFiles, '[') === 0 && strpos($quotationFiles, 'media') !== false)) {
            logMessage("Converting to plain string format");
            
            // Check if it's already in JSON format
            $decoded = json_decode($quotationFiles, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded[0])) {
                // It's JSON format, extract the path and convert to plain string
                $existingPath = $decoded[0];
                logMessage("Existing JSON path: $existingPath");
                
                // Clean up the path - remove any double escaping and leading slashes
                $cleanPath = str_replace('\\/', '/', $existingPath); // Fix double escaping
                $cleanPath = ltrim($cleanPath, '/');
                
                $formattedPath = formatUrlCorrectly($cleanPath);
                
                logMessage("Converted to plain string: $formattedPath");
            } else {
                // It's a plain text path but might need cleanup
                $cleanPath = ltrim($quotationFiles, '/');
                $formattedPath = formatUrlCorrectly($cleanPath);
                
                logMessage("Formatting plain text path: $formattedPath");
            }
            
            // Update database with correctly formatted URL
            $stmt = $mysqli->prepare("UPDATE $tableName SET quotation_files = ? WHERE id = ?");
            $stmt->bind_param('si', $formattedPath, $recordId);
            
            if ($stmt->execute()) {
                logMessage("✅ Successfully fixed local path format for $ordenDeTrabajo (ID: $recordId)", 'success');
                $localPathFixed++;
            } else {
                logMessage("❌ Failed to update format for $ordenDeTrabajo: " . $stmt->error, 'error');
                $errors++;
            }
            $stmt->close();
        } else {
            logMessage("⚠️ Unknown format - skipping: " . substr($quotationFiles, 0, 50), 'warning');
            $skipped++;
        }

        // Show progress every 50 records
        if ($processedCount % 50 == 0) {
            logMessage("Progress: $processedCount/$totalRecords records processed");
        }
    }

    // Display final results
    echo "\n==========================================\n";
    echo "  FIX RESULTS\n";
    echo "==========================================\n";
    logMessage("📊 Total records processed: $totalRecords", 'info');
    logMessage("🔄 Google Drive URLs fixed: $googleDriveFixed", 'success');
    logMessage("🔧 Local paths fixed: $localPathFixed", 'success');
    logMessage("✅ Already correct format: $alreadyCorrect", 'info');
    logMessage("⚠️ Skipped (no mapping/unknown): $skipped", 'warning');
    logMessage("❌ Errors: $errors", 'error');
    
    $totalFixed = $googleDriveFixed + $localPathFixed;
    
    logMessage("📊 Total records fixed: $totalFixed", 'success');
    logMessage("📊 Total errors: $errors", 'error');
    
    if ($errors > 0) {
        logMessage("Check the log file for detailed error information: $logFile", 'warning');
    }

    logMessage("Fix process completed at: " . date('Y-m-d H:i:s'));
    echo "==========================================\n";

} catch (Exception $e) {
    logMessage("❌ Fatal error: " . $e->getMessage(), 'error');
    exit(1);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}

logMessage("Script execution completed");
?>
