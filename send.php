<?php
// CSRF簡易対策: Refererチェック
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowed = ['lancru300kaitori.jp', 'localhost', '127.0.0.1'];
$ok = false;
foreach ($allowed as $h) {
    if (strpos($referer, $h) !== false) { $ok = true; break; }
}
// POSTかつJSON acceptのみ通す
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Method Not Allowed']));
}

header('Content-Type: application/json; charset=utf-8');
// CORS（同一ドメインのみ）
header('Access-Control-Allow-Origin: http://lancru300kaitori.jp');

// 送信先
$to      = 'carshopglory.yasuda@gmail.com';
$from_name = 'ランクル300専門高価買取JP 査定フォーム';
$from_addr = 'noreply@lancru300kaitori.jp';

// 入力取得 (JSON or POST)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

// サニタイズ関数
function s($v) {
    if (is_array($v)) {
        return implode('、', array_map(fn($x) => htmlspecialchars(strip_tags(trim($x)), ENT_QUOTES, 'UTF-8'), $v));
    }
    return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8');
}

// 必須チェック
$required = ['name', 'tel', 'email', 'model', 'year', 'mileage_range', 'smoking', 'sell_timing', 'contact_method'];
foreach ($required as $f) {
    if (empty($data[$f])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '必須項目が未入力です: ' . $f]);
        exit;
    }
}

// メール本文組み立て
$subject = '【無料査定申込】' . s($data['name']) . ' 様より査定依頼';

$body  = "■ お車の情報\n";
$body .= "車種　　　：" . s($data['model']) . "\n";
$body .= "グレード　：" . s($data['grade'] ?? '') . "\n";
$body .= "装備　　　：" . s($data['equipment[]'] ?? ($data['equipment'] ?? '')) . "\n";
$body .= "その他装備：" . s($data['equipment_other'] ?? '') . "\n";
$body .= "年式　　　：" . s($data['year']) . " " . s($data['month'] ?? '') . "\n";
$body .= "走行距離　：" . s($data['mileage_range']) . "\n";
$body .= "走行距離(詳)：" . s($data['mileage_exact'] ?? '') . " km\n";
$body .= "車体カラー：" . s($data['color'] ?? '') . "\n";
$body .= "他社査定額：" . s($data['other_estimate'] ?? '') . "\n";
$body .= "希望金額　：" . s($data['hope_price'] ?? '') . "\n";
$body .= "購入時状態：" . s($data['purchase_condition'] ?? '') . "\n";
$body .= "板金・修復：" . s($data['repair'] ?? '') . "\n";
$body .= "ダメージ詳細：" . s($data['damage_detail'] ?? '') . "\n";
$body .= "喫煙状態　：" . s($data['smoking']) . "\n";
$body .= "売却予定　：" . s($data['sell_timing']) . "\n";
$body .= "\n■ お客様情報\n";
$body .= "お名前　　：" . s($data['name']) . "\n";
$body .= "フリガナ　：" . s($data['kana'] ?? '') . "\n";
$body .= "電話番号　：" . s($data['tel']) . "\n";
$body .= "メール　　：" . s($data['email']) . "\n";
$body .= "連絡方法　：" . s($data['contact_method']) . "\n";
$body .= "備考　　　：" . s($data['message'] ?? '') . "\n";
$body .= "\n送信日時：" . date('Y-m-d H:i:s') . "\n";

// ヘッダー
$headers  = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_addr}>\r\n";
$headers .= "Reply-To: " . s($data['email']) . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: base64\r\n";

// mb_send_mail（さくらはmb_send_mailが確実）
mb_language('Japanese');
mb_internal_encoding('UTF-8');

$result = mb_send_mail($to, $subject, $body, $headers);

if ($result) {
    // 自動返信
    $reply_subject = '【ランクル300専門高価買取JP】査定申込を受け付けました';
    $reply_body  = s($data['name']) . " 様\n\n";
    $reply_body .= "この度はランクル300専門高価買取JPへお問い合わせいただきありがとうございます。\n";
    $reply_body .= "担当者より改めてご連絡いたします。\n\n";
    $reply_body .= "【受付内容】\n";
    $reply_body .= "車種：" . s($data['model']) . "\n";
    $reply_body .= "年式：" . s($data['year']) . "\n";
    $reply_body .= "走行距離：" . s($data['mileage_range']) . "\n\n";
    $reply_body .= "ご不明な点はお電話またはLINEにてお問い合わせください。\n";
    $reply_body .= "TEL: 0586-47-655（受付 10:00-18:00 / 火曜定休）\n\n";
    $reply_body .= "━━━━━━━━━━━━━━━━━━\n";
    $reply_body .= "ランクル300専門高価買取JP\n";
    $reply_body .= "カーショップグローリー（株式会社ライジングサン）\n";
    $reply_body .= "https://lancru300kaitori.jp/\n";
    $reply_body .= "━━━━━━━━━━━━━━━━━━\n";

    $reply_headers  = "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_addr}>\r\n";
    $reply_headers .= "MIME-Version: 1.0\r\n";
    $reply_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mb_send_mail(s($data['email']), $reply_subject, $reply_body, $reply_headers);

    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'メール送信に失敗しました。お電話またはLINEでご連絡ください。']);
}
