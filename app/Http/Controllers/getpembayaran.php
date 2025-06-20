<?php
include_once("header/header.php");
include_once("config/config.php");
include_once("config/check_db.php");
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}



date_default_timezone_set('Asia/Jakarta');

$koneksi1 = conn_semua();

function GetValue($tablename, $column, $where)
{
    global $koneksi1;
    $sql = "SELECT $column FROM $tablename WHERE $where";
    $rowult_get_value = mysqli_query($koneksi1, $sql);
    $row_get_value = mysqli_fetch_row($rowult_get_value);
    return $row_get_value[0];
}

// $nis_student = isset($_REQUEST['student_nis']) ? $_REQUEST['student_nis'] : '';
// $kode_sekolah = isset($_REQUEST['kode_sekolah']) ? $_REQUEST['kode_sekolah'] : '';

$varjson = json_decode(file_get_contents("php://input")); //Ambil variabel JSON
// print_r($varjson);
// exit;
$nis                = $varjson->nis;
$kode_sekolah       = $varjson->kode_sekolah;
$period_id          = $varjson->period_id;

$nama_sekolah = GetValue("sekolahs", "nama_sekolah", "kode_sekolah='$kode_sekolah'");
$alamat_sekolah = GetValue("sekolahs", "alamat_sekolah", "kode_sekolah='$kode_sekolah'");
$database = GetValue("sekolahs", "db", "kode_sekolah='$kode_sekolah'") . ".";
$domain = GetValue("sekolahs", "folder", "kode_sekolah='$kode_sekolah'");
$waktuindonesia = GetValue("sekolahs", "waktu_indonesia", "kode_sekolah='$kode_sekolah'");
$payment_gateway = GetValue("sekolahs", "payment_gateway", "kode_sekolah='$kode_sekolah'");

$koneksi = select_conn($database);

function GetValue_2($tablename, $column, $where)
{
    global $koneksi;
    $sql = "SELECT $column FROM $tablename WHERE $where";
    $rowult_get_value = mysqli_query($koneksi, $sql);
    $row_get_value = mysqli_fetch_row($rowult_get_value);
    return $row_get_value[0];
}

$match_string_period = implode(",", $period_id);

$sql_siswaID = "select student.student_id
from " . $database . "student
where student_nis='$nis'";

$read =  mysqli_query($koneksi, $sql_siswaID);
$sr = mysqli_fetch_assoc($read);
$siswaID = $sr['student_id'];

$fliptransaksi_exist = "SELECT * FROM information_schema.tables WHERE table_schema = '" . str_replace('.', '', $database) . "' AND table_name = 'flip_transaksi' LIMIT 1";
$exist = mysqli_query($koneksi, $fliptransaksi_exist);
$existfliptransaksi = mysqli_num_rows($exist);

if ($existfliptransaksi > 0) {

    $sql_flip = "SELECT s . pos_name,
		d . period_id,
		d . period_start,
		d . period_end,
		p . payment_id,
		SUM(b . bulan_bill) as total,
		SUM(IF(b . bulan_status = '1', b . bulan_bill, 0)) as dibayar
	FROM " . $database . "bulan as b
	JOIN " . $database . "payment as p ON b . payment_payment_id = p . payment_id
	JOIN " . $database . "pos as s ON s . pos_id = p . pos_pos_id
	LEFT JOIN " . $database . "account as a ON a . account_id = b . bulan_account_id
	JOIN " . $database . "period as d ON d . period_id = p . period_period_id
	JOIN " . $database . "student as t ON t . student_id = b.student_student_id
	JOIN " . $database . "month as m ON m . month_id = b.month_month_id
	WHERE d . period_id in ($match_string_period) AND student_student_id = '$siswaID'
	GROUP BY p . payment_id";

    $tes = mysqli_query($koneksi, $sql_flip);
    while ($row = mysqli_fetch_array($tes)) {
        $namePay = $row['pos_name'] . ' - T.A ' . $row['period_start'] . '/' . $row['period_end'];
        $payid = $row['payment_id'];
        $jml  = $row['total'];
        $dbyr = $row['dibayar'];

        $bulanan = "SELECT t . student_id,
			t . student_nis,
			t . student_full_name,
			s . pos_name,
			d . period_id,
			d . period_start,
			d . period_end,
			p . payment_id,
			p . payment_mode,
			b . month_month_id,
			b . bulan_bill,
			b . bulan_date_pay,
			b . bulan_status,
			m . month_name,
			a . account_description,
			b . bulan_id,
			CONCAT(pos_name, ' ', month_name, ' TA ', period_start, '/', period_end) txt,
			b.flip_no_trans,
			b.flip_status
		FROM " . $database . "bulan as b
		JOIN " . $database . "payment as p ON b . payment_payment_id = p . payment_id
		JOIN " . $database . "pos as s ON s . pos_id = p . pos_pos_id
		LEFT JOIN " . $database . "account as a ON a . account_id = b . bulan_account_id
		JOIN " . $database . "period as d ON d . period_id = p . period_period_id
		JOIN " . $database . "student as t ON t . student_id = b.student_student_id
		JOIN " . $database . "month as m ON m . month_id = b.month_month_id
		WHERE d . period_id in ($match_string_period)
		AND student_student_id = '$siswaID'
		AND p . payment_id = '$payid'
		AND bulan_status = '0'
		ORDER BY b.month_month_id";

        $hasil = mysqli_query($koneksi, $bulanan);
        if (mysqli_num_rows($hasil) > 0) {
            while ($row = mysqli_fetch_assoc($hasil)) {
                $detail_bulan[] = array(
                    "detail_bulan" => array(
                        "row"             => $row['txt'],
                        "bulan_bill"      => $row['bulan_bill'],
                        "bulan_id"        => $row['bulan_id'],
                        "period"          => $row['period_id'],
                        "status"          => (isset($row['flip_status'])) ? true : false,
                    )
                );
            }
        }
    }

    $num = mysqli_num_rows($tes);
    $num_bulanan = mysqli_num_rows($hasil);

    if ($num != 0 && $num_bulanan != 0) {

        // $row = mysqli_fetch_array($res);

        // $idstudent=$siswaID;

        $obj = (object) [
            'is_correct' => true,
            'pembayaran' => 'bulanan',
            'detail'     => $detail_bulan,
            "payment"    => $payment_gateway,
            'message'    => 'Data ada'
        ];
    } else if ($num != 0 && $num_bulanan == 0) {
        $obj = (object) [
            'is_correct'      => true,
            'detail'          => [],
            'message'         => 'Data anda kosong'
        ];
    } else {
        $obj = (object) [
            'is_correct' => false,
            'message' => 'Data tidak ada'
        ];
    }

    echo json_encode($obj, JSON_PRETTY_PRINT);
} else {

    $sql = "SELECT s . pos_name,
		d . period_id,
		d . period_start,
		d . period_end,
		p . payment_id,
		SUM(b . bulan_bill) as total,
		SUM(IF(b . bulan_status = '1', b . bulan_bill, 0)) as dibayar
	FROM " . $database . "bulan as b
	JOIN " . $database . "payment as p ON b . payment_payment_id = p . payment_id
	JOIN " . $database . "pos as s ON s . pos_id = p . pos_pos_id
	LEFT JOIN " . $database . "account as a ON a . account_id = b . bulan_account_id
	JOIN " . $database . "period as d ON d . period_id = p . period_period_id
	JOIN " . $database . "student as t ON t . student_id = b.student_student_id
	JOIN " . $database . "month as m ON m . month_id = b.month_month_id
	WHERE d . period_id in ($match_string_period) AND student_student_id = '$siswaID'
	GROUP BY p . payment_id";

    $tes = mysqli_query($koneksi, $sql);
    while ($row = mysqli_fetch_array($tes)) {
        $namePay = $row['pos_name'] . ' - T.A ' . $row['period_start'] . '/' . $row['period_end'];
        $payid = $row['payment_id'];
        $jml  = $row['total'];
        $dbyr = $row['dibayar'];

        $bulanan = "SELECT t . student_id,
			t . student_nis,
			t . student_full_name,
			s . pos_name,
			d . period_id,
			d . period_start,
			d . period_end,
			p . payment_id,
			p . payment_mode,
			b . month_month_id,
			b . bulan_bill,
			b . bulan_date_pay,
			b . bulan_status,
			m . month_name,
			a . account_description,
			b . bulan_id,
			CONCAT(pos_name, ' ', month_name, ' TA ', period_start, '/', period_end) txt,
			b.ipaymu_no_trans,
			b.ipaymu_status
		FROM " . $database . "bulan as b
		JOIN " . $database . "payment as p ON b . payment_payment_id = p . payment_id
		JOIN " . $database . "pos as s ON s . pos_id = p . pos_pos_id
		LEFT JOIN " . $database . "account as a ON a . account_id = b . bulan_account_id
		JOIN " . $database . "period as d ON d . period_id = p . period_period_id
		JOIN " . $database . "student as t ON t . student_id = b.student_student_id
		JOIN " . $database . "month as m ON m . month_id = b.month_month_id
		WHERE d . period_id in ($match_string_period)
		AND student_student_id = '$siswaID'
		AND p . payment_id = '$payid'
		AND bulan_status = '0'
		ORDER BY b.month_month_id";

        $hasil = mysqli_query($koneksi, $bulanan);
        if (mysqli_num_rows($hasil) > 0) {
            while ($row = mysqli_fetch_assoc($hasil)) {
                $detail_bulan[] = array(
                    "detail_bulan" => array(
                        "row"             => $row['txt'],
                        "bulan_bill"      => $row['bulan_bill'],
                        "bulan_id"        => $row['bulan_id'],
                        "period"          => $row['period_id'],
                        "status"          => (isset($row['ipaymu_status'])) ? true : false,
                    )
                );
            }
        }
    }

    $num = mysqli_num_rows($tes);
    $num_bulanan = mysqli_num_rows($hasil);

    if ($num != 0 && $num_bulanan != 0) {
        $obj = (object) [
            'is_correct' => true,
            'pembayaran' => 'bulanan',
            'detail'     => $detail_bulan,
            "payment"    => $payment_gateway,
            'message'    => 'Data ada'
        ];
    } else if ($num != 0 && $num_bulanan == 0) {
        $obj = (object) [
            'is_correct'      => true,
            'detail'          => [],
            'message'         => 'Data anda kosong'
        ];
    } else {
        $obj = (object) [
            'is_correct' => false,
            'message' => 'Data tidak ada'
        ];
    }

    echo json_encode($obj, JSON_PRETTY_PRINT);
}
