<?php

/**
 *
 * TODO:
 *
 * product_id text por_0001
 *
 * страницы админки (CMS)
 * + менять пароли для БД
 *
 */

require_once('../vendor/autoload.php');
if (!array_key_exists('PHP_ENV', $_ENV) || $_ENV['PHP_ENV'] !== 'production') {
    $dotenv = new Dotenv\Dotenv(__DIR__ . DIRECTORY_SEPARATOR . '..');
    $dotenv->load();
}

$s3 = Aws\S3\S3Client::factory([
    'version' => 'latest',
    'region' => 'us-east-1'
]);
$bucket = getenv('S3_BUCKET_NAME') ?: die('No "S3_BUCKET" config var in found in env!');

function not_empty_get(array $items)
{
    foreach ($items as $item) {
        if (!array_key_exists($item, $_GET)) {
            return false;
        }
        if (empty($_GET[$item])) {
            return false;
        }
    }

    return true;
}

function thumbnailImage($imagePath)
{
    $imagick = new \Imagick(realpath($imagePath));
    $imagick->setbackgroundcolor('rgb(64, 64, 64)');
    $imagick->thumbnailImage(100, 100, true, true);

    return $imagick->getImageBlob();
}

require_once('../include/db.php');

switch ($_GET['type']) {
    case 'get':
        $where = [];
        $where_sql = '';
        if (!empty($_GET['edition_name'])) $where['edition_name'] = $_GET['edition_name'];
        if (!empty($_GET['model_number'])) $where['model_number'] = $_GET['model_number'];
        if (!empty($_GET['model_name'])) $where['model_name'] = $_GET['model_name'];
        if (!empty($_GET['shoot_name'])) $where['shoot_name'] = $_GET['shoot_name'];
        if (!empty($where)) {
            $where_sql = ' WHERE ';
            $i = 0;
            foreach ($where as $key => $value) {
                $i++;
                $where_sql .= " $key = '" . pg_escape_string($value) . "' ";
                if ($i < count($where)) $where_sql .= ' AND ';
            }
        }
        switch ($_GET['table']) {
            case 'editions':
            case 'edition_menu':
            case 'images_menu':
            case 'social_networks':
            case 'subscriptions_menu':
            case 'videos_menu':
                $r = pg_query($db, "SELECT * FROM {$_GET['table']} $where_sql ORDER BY id ASC");
                echo json_encode(pg_num_rows($r) > 0 ? pg_fetch_all($r) : []);
                break;
            case 'rules':
                $r = pg_query($db, "SELECT * FROM {$_GET['table']} $where_sql ORDER BY id ASC");
                echo '<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
             <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
                         <meta http-equiv="X-UA-Compatible" content="ie=edge">
             <title>Terms Of Service</title>
</head>
<body>' . pg_fetch_all($r)[0]['text'] . '</body></html>';
                break;
            default:
                die(404);
                break;
        }
        break;

    case 'add':
        switch ($_GET['table']) {
            case 'editions':
                if (!empty($_POST['edition_name']) && !empty($_POST['prefix'])) {
                    pg_query($db, "
                        INSERT INTO {$_GET['table']} 
                        VALUES (
                            '{$_POST['edition_name']}', DEFAULT, '{$_POST['prefix']}'
                        )"
                    );
                }
                break;
            case 'edition_menu':
                $post_fields = array(
                    'edition_name',
                    'model_number',
                    'model_name',
                    'shoot_name',
                    '(upl)video_button',
                    '(upl)subscription_button',
                    '(upl)image_button'
                );

                $sql_fields = array();

                $fields_not_empty = true;
                foreach ($post_fields as $field) {
                    if (substr($field, 0, strlen('(upl)')) === '(upl)') {
                        $field = substr($field, strlen('(upl)'));
                        if (empty($_FILES[$field])) {
                            $fields_not_empty = false;
                        }
                        $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                        array_push($sql_fields, $upload->get('ObjectURL'));
                    } else {
                        if (empty($_POST[$field])) {
                            $fields_not_empty = false;
                        }
                        array_push($sql_fields, $_POST[$field]);
                    }
                }

                if ($fields_not_empty) {
                    $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                    pg_query($db, $query);
                }
                break;
            case 'images_menu':
                $post_fields = array(
                    'edition_name',
                    'model_number',
                    'model_name',
                    'shoot_name',
                    '(gen)thumbnail',
                    '(upl)download_image',
                    'product_id',
                    'price_gbp',
                    'price_usd',
                    'price_eur'
                );

                $sql_fields = array(
                    'edition_name' => null,
                    'model_number' => null,
                    'model_name' => null,
                    'shoot_name' => null,
                    'thumbnail' => null,
                    'download_image' => null,
                    'product_id' => null,
                    'price_gbp' => null,
                    'price_usd' => null,
                    'price_eur' => null
                );

                $batch = array_key_exists('batch', $_GET) && $_GET['batch'] === 'true';
                $product_id_prefix = 'NULL';

                $fields_not_empty = true;
                foreach ($post_fields as $field) {
                    if (substr($field, 0, 5) === '(upl)') {
                        $field = substr($field, 5);
                        if (empty($_FILES[$field])) {
                            $fields_not_empty = false;
                        } else {
                            if ($batch) {
                                for ($i = 0; $i < count($_FILES[$field]['name']); $i++) {
                                    $upload = $s3->upload($bucket, $_FILES[$field]['name'][$i], fopen($_FILES[$field]['tmp_name'][$i], 'rb'), 'public-read');
                                    if (!array_key_exists('upload', $sql_fields)) {
                                        $sql_fields['upload'] = [];
                                    }
                                    array_push($sql_fields['upload'], $upload->get('ObjectURL'));
                                }
                            } else {
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, $upload->get('ObjectURL'));
                            }
                        }
                    } elseif (substr($field, 0, strlen('(gen)')) === '(gen)') {
                        $field = substr($field, strlen('(gen)'));
                        if (empty($_FILES['download_image'])) {
                            $fields_not_empty = false;
                        } else {
                            if ($batch) {
                                for ($i = 0; $i < count($_FILES['download_image']['name']); $i++) {
                                    $data = thumbnailImage($_FILES['download_image']['tmp_name'][$i]);
                                    $upload = $s3->upload($bucket, "{$_FILES['download_image']['name'][$i]}_thumbnail", $data, 'public-read');
                                    if (!array_key_exists('upload_thumbnail', $sql_fields)) {
                                        $sql_fields['upload_thumbnail'] = [];
                                    }
                                    array_push($sql_fields['upload_thumbnail'], $upload->get('ObjectURL'));
                                }
                            } else {
                                $data = thumbnailImage($_FILES['download_image']['tmp_name']);
                                $upload = $s3->upload($bucket, "{$_FILES['download_image']['name']}_thumbnail", $data, 'public-read');
                                array_push($sql_fields, $upload->get('ObjectURL'));
                            }
                        }
                    } elseif ($batch && $field === 'product_id') {
                        $product_id_prefix = $_POST[$field];
                    } else {
                        if (empty($_POST[$field])) {
                            $fields_not_empty = false;
                        }
                        $sql_fields[$field] = $_POST[$field];
                    }
                }

                if ($fields_not_empty) {
                    if ($batch) {
                        $r = pg_query($db, "SELECT MAX(CAST(RIGHT(product_id, 4) AS INTEGER)) as prod_id FROM {$_GET['table']} WHERE product_id LIKE '{$_POST['product_id']}%';");
                        $res = pg_fetch_assoc($r);
                        $prod_id = intval($res['prod_id']) + 1;

                        $upload_array = $sql_fields['upload'];
                        $upload_array_thumb = $sql_fields['upload_thumbnail'];
                        unset($sql_fields['upload']);
                        unset($sql_fields['upload_thumbnail']);

                        $i = 0;
                        foreach ($upload_array as $sql_field) {
                            $sql_fields['product_id'] = $product_id_prefix . str_pad(strval($prod_id), 4, '0', STR_PAD_LEFT);
                            $sql_fields['download_image'] = $sql_field;
                            $sql_fields['thumbnail'] = $upload_array_thumb[$i];
                            $prod_id++;

                            $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                            pg_query($db, $query);

                            $i++;
                        }
                    } else {
                        $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                        pg_query($db, $query);
                    }
                }
                break;
            case 'social_networks':
                $post_fields = array(
                    'name',
                    'url',
                    '(upl)icon_color',
                    '(upl)thumbnail_grey'
                );

                $sql_fields = array();

                $fields_not_empty = true;
                foreach ($post_fields as $field) {
                    if (substr($field, 0, 5) === '(upl)') {
                        $field = substr($field, 5);
                        if (empty($_FILES[$field])) {
                            $fields_not_empty = false;
                        }
                        $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                        array_push($sql_fields, $upload->get('ObjectURL'));
                    } else {
                        if (empty($_POST[$field])) {
                            $fields_not_empty = false;
                        }
                        array_push($sql_fields, $_POST[$field]);
                    }
                }

                if ($fields_not_empty) {
                    $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                    pg_query($db, $query);
                }
                break;
            case 'subscriptions_menu':
                $post_fields = array(
                    'edition_name',
                    'model_number',
                    'model_name',
                    'shoot_name',
                    '(gen)thumbnail',
                    '(upl)subscription_image',
                    'product_id'
                );

                $sql_fields = array(
                    'edition_name' => null,
                    'model_number' => null,
                    'model_name' => null,
                    'shoot_name' => null,
                    'thumbnail' => null,
                    'subscription_image' => null,
                    'product_id' => null
                );

                $batch = array_key_exists('batch', $_GET) && $_GET['batch'] === 'true';
                $product_id_prefix = 'NULL';

                $fields_not_empty = true;
                foreach ($post_fields as $field) {
                    if (substr($field, 0, strlen('(upl)')) === '(upl)') {
                        $field = substr($field, strlen('(upl)'));
                        if (empty($_FILES[$field])) {
                            $fields_not_empty = false;
                        } else {
                            if ($batch) {
                                for ($i = 0; $i < count($_FILES[$field]['name']); $i++) {
                                    $upload = $s3->upload($bucket, $_FILES[$field]['name'][$i], fopen($_FILES[$field]['tmp_name'][$i], 'rb'), 'public-read');
                                    if (!array_key_exists('upload', $sql_fields)) {
                                        $sql_fields['upload'] = [];
                                    }
                                    array_push($sql_fields['upload'], $upload->get('ObjectURL'));
                                }
                            } else {
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, $upload->get('ObjectURL'));
                            }
                        }
                    } elseif (substr($field, 0, strlen('(gen)')) === '(gen)') {
                        $field = substr($field, strlen('(gen)'));
                        if (empty($_FILES['subscription_image'])) {
                            $fields_not_empty = false;
                        } else {
                            if ($batch) {
                                for ($i = 0; $i < count($_FILES['subscription_image']['name']); $i++) {
                                    $data = thumbnailImage($_FILES['subscription_image']['tmp_name'][$i]);
                                    $upload = $s3->upload($bucket, "{$_FILES['subscription_image']['name'][$i]}_thumbnail", $data, 'public-read');
                                    if (!array_key_exists('upload_thumbnail', $sql_fields)) {
                                        $sql_fields['upload_thumbnail'] = [];
                                    }
                                    array_push($sql_fields['upload_thumbnail'], $upload->get('ObjectURL'));
                                }
                            } else {
                                $data = thumbnailImage($_FILES['subscription_image']['tmp_name']);
                                $upload = $s3->upload($bucket, "{$_FILES['subscription_image']['name']}_thumbnail", $data, 'public-read');
                                array_push($sql_fields, $upload->get('ObjectURL'));
                            }
                        }
                    } elseif ($batch && $field === 'product_id') {
                        $product_id_prefix = $_POST[$field];
                    } else {
                        if (empty($_POST[$field])) {
                            $fields_not_empty = false;
                        }
                        $sql_fields[$field] = $_POST[$field];
                    }
                }

                if ($fields_not_empty) {
                    if ($batch) {
                        $r = pg_query($db, "SELECT MAX(CAST(RIGHT(product_id, 4) AS INTEGER)) as prod_id FROM {$_GET['table']} WHERE product_id LIKE '{$_POST['product_id']}%';");
                        $res = pg_fetch_assoc($r);
                        $prod_id = intval($res['prod_id']) + 1;

                        $upload_array = $sql_fields['upload'];
                        $upload_array_thumb = $sql_fields['upload_thumbnail'];
                        unset($sql_fields['upload']);
                        unset($sql_fields['upload_thumbnail']);

                        $i = 0;
                        foreach ($upload_array as $sql_field) {
                            $sql_fields['product_id'] = $product_id_prefix . str_pad(strval($prod_id), 4, '0', STR_PAD_LEFT);
                            $sql_fields['subscription_image'] = $sql_field;
                            $sql_fields['thumbnail'] = $upload_array_thumb[$i];
                            $prod_id++;

                            $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                            pg_query($db, $query);

                            $i++;
                        }
                    } else {
                        $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                        pg_query($db, $query);
                    }
                }
                break;
            case 'videos_menu':
                $post_fields = array(
                    'edition_name',
                    'model_number',
                    'model_name',
                    'shoot_name',
                    'video_title',
                    '(len)length',
                    '(size)size',
                    'price_gbp',
                    'price_usd',
                    'price_eur',
                    '(upl)thumbnail',
                    '(upl)video',
                    'product_id'
                );

                $sql_fields = array(
                    'edition_name' => null,
                    'model_number' => null,
                    'model_name' => null,
                    'shoot_name' => null,
                    'video_title' => null,
                    'length' => null,
                    'size' => null,
                    'price_gbp' => null,
                    'price_usd' => null,
                    'price_eur' => null,
                    'thumbnail' => null,
                    'video' => null,
                    'product_id' => null
                );

                //$batch = array_key_exists('batch', $_GET) && $_GET['batch'] === 'true';

                $product_id_prefix = $_POST['product_id'];
                $r = pg_query($db, "SELECT MAX(CAST(RIGHT(product_id, 4) AS INTEGER)) as prod_id FROM {$_GET['table']} WHERE product_id LIKE '{$_POST['product_id']}%';");
                $res = pg_fetch_assoc($r);
                $prod_id = intval($res['prod_id']) + 1;

                $edition_name = $_POST['edition_name'];
                $model_number = $_POST['model_number'];
                $model_name = $_POST['model_name'];
                $shoot_name = $_POST['shoot_name'];
                $video_title = $_POST['video_title'];

                $price_gbp = $_POST['price_gbp'];
                $price_usd = $_POST['price_usd'];
                $price_eur = $_POST['price_eur'];

                $upload_array = [];
                for ($i = 0; $i < count($_FILES['thumbnail']['name']); $i++) {
                    $upload_thumbnail = $s3->upload($bucket, $_FILES['thumbnail']['name'][$i], fopen($_FILES['thumbnail']['tmp_name'][$i], 'rb'), 'public-read');
                    $upload_video = $s3->upload($bucket, $_FILES['video']['name'][$i], fopen($_FILES['video']['tmp_name'][$i], 'rb'), 'public-read');
                    $ffprobe = FFMpeg\FFProbe::create();
                    $duration = $ffprobe->format($_FILES['video']['tmp_name'][$i])->get('duration');
                    $upload_array[$i] = [
                        'thumbnail' => $upload_thumbnail->get('ObjectURL'),
                        'video' => $upload_video->get('ObjectURL'),
                        'product_id' => "{$product_id_prefix} {$prod_id}",
                        'length' => intval($duration / 60),
                        'size' => intval($_FILES['video']['size'][$i] / 1024 / 1024)
                    ];
                    $prod_id++;
                }

                foreach ($upload_array as $item) {
                    $query = "
INSERT INTO {$_GET['table']} VALUES (
  '{$edition_name}',
  '{$model_number}',
  '{$model_name}',
  '{$shoot_name}',
  '{$video_title}',
  '{$item['length']}',
  '{$item['size']}',
  '{$price_gbp}',
  '{$price_usd}',
  '{$price_eur}',
  '{$item['thumbnail']}',
  '{$item['video']}',
  '{$item['product_id']}'
)";
                    pg_query($db, $query);
                }


                /*$fields_not_empty = true;*/
                /*foreach ($post_fields as $field) {
                    if (substr($field, 0, strlen('(upl)')) === '(upl)') {
                        $field = substr($field, strlen('(upl)'));
                        if (empty($_FILES[$field])) {
                            $fields_not_empty = false;
                        } else {
                            if ($batch) {
                                for ($i = 0; $i < count($_FILES[$field]['name']); $i++) {
                                    $upload = $s3->upload($bucket, $_FILES[$field]['name'][$i], fopen($_FILES[$field]['tmp_name'][$i], 'rb'), 'public-read');
                                    if (!array_key_exists("upload_$field", $sql_fields)) $sql_fields["upload_$field"] = [];
                                    array_push($sql_fields["upload_$field"], $upload->get('ObjectURL'));
                                }
                            } else {
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, $upload->get('ObjectURL'));
                            }
                        }
                    } elseif (substr($field, 0, strlen('(len)')) === '(len)') {
                        if (empty($_FILES['video'])) {
                            $fields_not_empty = false;
                        } else {
                            $field = substr($field, strlen('(len)'));
                            if ($batch) {
                                for ($i = 0; $i < count($_FILES['video']['name']); $i++) {
                                    $ffprobe = FFMpeg\FFProbe::create();
                                    $duration = $ffprobe->format($_FILES['video']['tmp_name'][$i])->get('duration');
                                    if (!array_key_exists('video_len', $sql_fields)) $sql_fields['video_len'] = [];
                                    array_push($sql_fields['video_len'], intval($duration));
                                }
                            } else {
                                $ffprobe = FFMpeg\FFProbe::create();
                                $duration = $ffprobe->format($_FILES['video']['tmp_name'])->get('duration');
                                array_push($sql_fields, intval($duration));
                            }
                        }
                    } elseif (substr($field, 0, strlen('(size)')) === '(size)') {
                        if (empty($_FILES['video'])) {
                            $fields_not_empty = false;
                        } else {
                            $field = substr($field, strlen('(size)'));
                            if ($batch) {
                                for ($i = 0; $i < count($_FILES['video']['name']); $i++) {
                                    $size = $_FILES['video']['size'][$i];
                                    if (!array_key_exists('video_size', $sql_fields)) $sql_fields['video_size'] = [];
                                    array_push($sql_fields['video_size'], $size);
                                }
                            } else {
                                $size = $_FILES['video']['size'];
                                array_push($sql_fields, intval($duration));
                            }
                        }
                    } elseif (substr($field, 0, strlen('(gen)')) === '(gen)') {
                        if (empty($_FILES['video'])) {
                            $fields_not_empty = false;
                        } else {
                            if ($batch) {
                                $field = substr($field, strlen('(gen)'));
                                for ($i = 0; $i < count($_FILES['video']['name']); $i++) {
                                    $ffmpeg = FFMpeg\FFMpeg::create();
                                    $video = $ffmpeg->open($_FILES['video']['tmp_name'][$i]);
                                    $ffprobe = FFMpeg\FFProbe::create();
                                    $duration = $ffprobe->format($_FILES['video']['tmp_name'][$i])->get('duration');
                                    $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(intval($duration / 2)));
                                    $thumbnail_name = "{$_FILES['video']['name'][$i]}_frame.jpg";
                                    $thumbnail_path = "/tmp/{$thumbnail_name}";
                                    $frame->save($thumbnail_path);
                                    $upload = $s3->upload($bucket, $thumbnail_name, fopen($thumbnail_path, 'rb'), 'public-read');
                                    if (!array_key_exists('video_thumbnail', $sql_fields)) $sql_fields['video_thumbnail'] = [];
                                    array_push($sql_fields['video_thumbnail'], $upload->get('ObjectURL'));
                                }
                            } else {
                                $ffmpeg = FFMpeg\FFMpeg::create();
                                $video = $ffmpeg->open($_FILES['video']['tmp_name']);
                                $ffprobe = FFMpeg\FFProbe::create();
                                $duration = $ffprobe->format($_FILES['video']['tmp_name'])->get('duration');
                                $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(intval($duration / 2)));
                                $thumbnail_name = "{$_FILES['video']['name']}_frame.jpg";
                                $thumbnail_path = "/tmp/{$thumbnail_name}";
                                $frame->save($thumbnail_path);
                                $upload = $s3->upload($bucket, $thumbnail_name, fopen($thumbnail_path, 'rb'), 'public-read');
                                array_push($sql_fields, $upload->get('ObjectURL'));
                            }
                        }
                    } elseif ($batch && $field === 'product_id') {
                        $product_id_prefix = $_POST[$field];
                    } else {
                        if (empty($_POST[$field])) $fields_not_empty = false;
                        $sql_fields[$field] = $_POST[$field];
                    }
                }*/

                /*if ($fields_not_empty) {
                    if ($batch) {
                        $r = pg_query($db, "SELECT MAX(CAST(RIGHT(product_id, 4) AS INTEGER)) as prod_id FROM {$_GET['table']} WHERE product_id LIKE '{$_POST['product_id']}%';");
                        $res = pg_fetch_assoc($r);
                        $prod_id = intval($res['prod_id']) + 1;

                        $upload_array = $sql_fields['upload_video'];
                        $upload_array_thumb = $sql_fields['thumbnail'];
                        $upload_array_size = $sql_fields['video_size'];
                        $upload_array_len = $sql_fields['video_len'];
                        unset($sql_fields['upload_video']);
                        unset($sql_fields['thumbnail']);
                        unset($sql_fields['video_size']);
                        unset($sql_fields['video_len']);

                        $i = 0;
                        foreach ($upload_array as $sql_field) {
                            $sql_fields['product_id'] = $product_id_prefix . str_pad(strval($prod_id), 4, '0', STR_PAD_LEFT);
                            $sql_fields['video'] = $sql_field;
                            $sql_fields['thumbnail'] = $upload_array_thumb[$i];
                            $sql_fields['size'] = $upload_array_size[$i];
                            $sql_fields['length'] = $upload_array_len[$i];
                            $prod_id++;

                            $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                            pg_query($db, $query);

                            $i++;
                        }
                    } else {
                        $query = "INSERT INTO {$_GET['table']} VALUES ('" . join("','", $sql_fields) . "')";
                        pg_query($db, $query);
                    }
                }*/
                break;
            default:
                die(404);
                break;
        }
        break;

    case 'edit':
        if (array_key_exists('id', $_GET) && intval($_GET['id']) > 0) {
            switch ($_GET['table']) {
                case 'editions':
                    $post_fields = array(
                        'edition_name',
                        'prefix',
                    );

                    $sql_fields = array();

                    foreach ($post_fields as $field) {
                        if (array_key_exists($field, $_POST)) {
                            array_push($sql_fields, "{$field}='{$_POST[$field]}'");
                        }
                    }

                    if (count($sql_fields) > 0) {
                        $query = "UPDATE {$_GET['table']} SET " . join(", ", $sql_fields) . " WHERE id = {$_GET['id']}";
                        pg_query($db, $query);
                    }
                    break;

                case 'edition_menu':
                    $post_fields = array(
                        'edition_name',
                        'model_number',
                        'model_name',
                        'shoot_name',
                        '(upl)video_button',
                        '(upl)subscription_button',
                        '(upl)image_button'
                    );

                    $sql_fields = array();
                    $images = array();

                    foreach ($post_fields as $field) {
                        if (substr($field, 0, strlen('(upl)')) === '(upl)') {
                            $field = substr($field, strlen('(upl)'));
                            if (!empty($_FILES[$field])) {
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                                array_push($images, array($field, $upload->get('ObjectURL')));
                            }
                        } else {
                            if (array_key_exists($field, $_POST)) {
                                array_push($sql_fields, "{$field}='{$_POST[$field]}'");
                            }
                        }
                    }

                    if (count($sql_fields) > 0) {
                        $query = "UPDATE {$_GET['table']} SET " . join(", ", $sql_fields) . " WHERE id = {$_GET['id']}";
                        pg_query($db, $query);
                    }

                    echo json_encode($images);
                    break;
                case 'images_menu':
                    $post_fields = array(
                        'edition_name',
                        'model_number',
                        'model_name',
                        'shoot_name',
                        '(gen)thumbnail',
                        '(upl)download_image',
                        'product_id',
                        'price_gbp',
                        'price_usd',
                        'price_eur'
                    );

                    $sql_fields = array();
                    $images = array();

                    foreach ($post_fields as $field) {
                        if (substr($field, 0, 5) === '(upl)') {
                            $field = substr($field, 5);
                            if (empty($_FILES[$field])) {
                                $fields_not_empty = false;
                            } else {
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                                array_push($images, array($field, $upload->get('ObjectURL')));
                            }
                        } elseif (substr($field, 0, strlen('(gen)')) === '(gen)') {
                            $field = substr($field, strlen('(gen)'));
                            if (empty($_FILES['download_image'])) {
                                $fields_not_empty = false;
                            } else {
                                $data = thumbnailImage($_FILES['download_image']['tmp_name']);
                                $upload = $s3->upload($bucket, "{$_FILES['download_image']['name']}_thumbnail", $data, 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                                array_push($images, array($field, $upload->get('ObjectURL')));
                            }
                        } else {
                            if (empty($_POST[$field])) {
                                $fields_not_empty = false;
                            }
                            $sql_fields[$field] = "{$field}='{$_POST[$field]}'";
                        }
                    }

                    if (count($sql_fields) > 0) {
                        $query = "UPDATE {$_GET['table']} SET " . join(", ", $sql_fields) . " WHERE id = {$_GET['id']}";
                        pg_query($db, $query);
                    }

                    echo json_encode($images);
                    break;
                case 'social_networks':
                    $post_fields = array(
                        'name',
                        'url',
                        '(upl)icon_color',
                        '(upl)thumbnail_grey'
                    );

                    $sql_fields = array();
                    $images = array();

                    foreach ($post_fields as $field) {
                        if (substr($field, 0, strlen('(upl)')) === '(upl)') {
                            $field = substr($field, strlen('(upl)'));
                            if (!empty($_FILES[$field])) {
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                                array_push($images, array($field, $upload->get('ObjectURL')));
                            }
                        } else {
                            if (array_key_exists($field, $_POST)) {
                                array_push($sql_fields, "{$field}='{$_POST[$field]}'");
                            }
                        }
                    }

                    if (count($sql_fields) > 0) {
                        $query = "UPDATE {$_GET['table']} SET " . join(", ", $sql_fields) . " WHERE id = {$_GET['id']}";
                        pg_query($db, $query);
                    }

                    echo json_encode($images);
                    break;
                case 'subscriptions_menu':
                    $post_fields = array(
                        'edition_name',
                        'model_number',
                        'model_name',
                        'shoot_name',
                        '(gen)thumbnail',
                        '(upl)subscription_image',
                        'product_id'
                    );

                    $sql_fields = array();
                    $images = array();

                    foreach ($post_fields as $field) {
                        if (substr($field, 0, strlen('(upl)')) === '(upl)') {
                            $field = substr($field, strlen('(upl)'));
                            if (empty($_FILES[$field])) {
                                $fields_not_empty = false;
                            } else {
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                            }
                        } elseif (substr($field, 0, strlen('(gen)')) === '(gen)') {
                            $field = substr($field, strlen('(gen)'));
                            if (empty($_FILES[$field])) {
                                $fields_not_empty = false;
                            } else {
                                $data = thumbnailImage($_FILES['subscription_image']['tmp_name']);
                                $upload = $s3->upload($bucket, "{$_FILES['subscription_image']['name']}_thumbnail", $data, 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                            }
                        } else {
                            if (empty($_POST[$field])) {
                                $fields_not_empty = false;
                            }
                            array_push($sql_fields, "{$field}='{$_POST[$field]}'");
                        }
                    }

                    if (count($sql_fields) > 0) {
                        $query = "UPDATE {$_GET['table']} SET " . join(", ", $sql_fields) . " WHERE id = {$_GET['id']}";
                        pg_query($db, $query);
                    }

                    echo json_encode($images);
                    break;
                case 'videos_menu':
                    $post_fields = array(
                        'edition_name',
                        'model_number',
                        'model_name',
                        'shoot_name',
                        'video_title',
                        '(len)length',
                        '(size)size',
                        'price_gbp',
                        'price_usd',
                        'price_eur',
                        '(upl)thumbnail',
                        '(upl)video',
                        'product_id'
                    );

                    $sql_fields = array();

                    foreach ($post_fields as $field) {
                        if (substr($field, 0, strlen('(upl)')) === '(upl)') {
                            $field = substr($field, strlen('(upl)'));
                            if (count($_FILES[$field]) > 0) {
                                if (empty($_FILES[$field])) {
                                    $fields_not_empty = false;
                                }
                                $upload = $s3->upload($bucket, (is_array($_FILES[$field]['name']) ? $_FILES[$field]['name'][0] : $_FILES[$field]['name']), fopen((is_array($_FILES[$field]['tmp_name']) ? $_FILES[$field]['tmp_name'][0] : $_FILES[$field]['tmp_name']), 'rb'), 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                            }
                        } elseif (substr($field, 0, strlen('(len)')) === '(len)') {
                            if (count($_FILES['video']) > 0) {
                                $field = substr($field, strlen('(len)'));
                                $ffprobe = FFMpeg\FFProbe::create();
                                $duration = $ffprobe->format($_FILES['video']['tmp_name'])->get('duration');
                                array_push($sql_fields, "{$field}='" . intval($duration / 60) . "'");
                            }
                        } elseif (substr($field, 0, strlen('(size)')) === '(size)') {
                            if (count($_FILES['video']) > 0) {
                                $field = substr($field, strlen('(size)'));
                                $size = intval($_FILES['video']['size'] / 1024 / 1024);
                                array_push($sql_fields, "{$field}='{$size}'");
                            }
                        } elseif (substr($field, 0, strlen('(gen)')) === '(gen)') {
                            if (count($_FILES) > 0) {
                                $field = substr($field, strlen('(gen)'));
                                $ffmpeg = FFMpeg\FFMpeg::create();
                                $video = $ffmpeg->open($_FILES['video']['tmp_name']);
                                $ffprobe = FFMpeg\FFProbe::create();
                                $duration = $ffprobe->format($_FILES['video']['tmp_name'])->get('duration');
                                $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(intval($duration / 2)));
                                $thumbnail_name = "{$_FILES['video']['name']}_frame.jpg";
                                $thumbnail_path = "/tmp/{$thumbnail_name}";
                                $frame->save($thumbnail_path);
                                $upload = $s3->upload($bucket, $thumbnail_name, fopen($thumbnail_path, 'rb'), 'public-read');
                                array_push($sql_fields, "{$field}='{$upload->get('ObjectURL')}'");
                            }
                        } else {
                            if (empty($_POST[$field])) {
                                $fields_not_empty = false;
                            } else {
                                array_push($sql_fields, "{$field}='{$_POST[$field]}'");
                            }
                        }
                    }

                    if (count($sql_fields) > 0) {
                        $query = "UPDATE {$_GET['table']} SET " . join(", ", $sql_fields) . " WHERE id = {$_GET['id']}";
                        pg_query($db, $query);
                    }

                    echo 'reload';
                    break;
                default:
                    die(404);
                    break;
            }
            break;
        } else {
            die(501);
        }

    case 'delete':
        if (array_key_exists('id', $_GET) && intval($_GET['id']) > 0) {
            switch ($_GET['table']) {
                case 'editions':
                case 'edition_menu':
                case 'images_menu':
                case 'social_networks':
                case 'subscriptions_menu':
                case 'videos_menu':
                    $r = pg_query($db, "DELETE FROM {$_GET['table']} WHERE id = {$_GET['id']}");
                    echo json_encode(pg_num_rows($r) > 0 ? pg_fetch_all($r) : []);
                    break;
                default:
                    die(404);
                    break;
            }
        } else {
            die(500);
        }
        break;

    default:
        die(404);
        break;
}
?>