<?php
require_once('config.php');


$accept = $_SERVER["HTTP_ACCEPT"];
$method = $_SERVER["REQUEST_METHOD"];
if (isset($_GET['id'])) {
    $id = $_GET['id'];
} else {
    $id = null;
}


switch ($method) {
    case "OPTIONS": {
        if ($id) {
            //When looking at a specific movie
            header('Allow: GET, OPTIONS, PUT, DELETE');
            exit;
        } else {
            //When looking at all movies
            header('Allow: POST, GET, OPTIONS');
            exit;
        }
        break;
    }

    case "GET": {

        if ($accept == "application/json" || $accept == "application/xml") {
        } else {
            http_response_code(415);
            exit;
        }

        if (isset($_GET['start'])) {
            $rowStart = (int)$_GET['start'];
        } else {
            $rowStart = 0;
        }

        if (isset($_GET['limit']) && $_GET['limit'] < 1000) {
            $limit = (int)$_GET['limit'];
        } else {
            $limit = 99999;
        }

        if ($id) {
            $query = "SELECT * FROM `movies` WHERE `id` = " . $id;
            $singleResult = true;
        } else {
            $query = "SELECT * FROM `movies` LIMIT " . $rowStart . ", " . $limit;
            $singleResult = false;
        }
        $results = mysqli_query($connect, $query);
        $resultArray = [];
        while ($row = mysqli_fetch_assoc($results)) {
            $resultArray[] = $row;
        }
        if (!isset($resultArray[0]["id"]) && $singleResult == true) {
            http_response_code(404);
            exit;
        }
        $query = "SELECT count(id) AS rows FROM `movies`";
        $results = mysqli_query($connect, $query);
        $row = mysqli_fetch_object($results);
        $recordNumber = $row->rows;


        fillData($resultArray, $accept, $singleResult, $recordNumber, $rowStart, $limit);
        break;
    }

    case "POST": {
        $content = $_SERVER["CONTENT_TYPE"];

        if ($content == "application/json") {
            $body = file_get_contents("php://input");
            $json = json_decode($body);
            if (isset($json->title, $json->synopsis, $json->director, $json->genre, $json->length, $json->releaseyear)) {
                $query = "INSERT INTO `movies` (title, synopsis, director, genre, length, releaseyear) VALUES ('" . $json->title . "', '" . $json->synopsis . "', '" . $json->director . "', '" . $json->genre . "', '" . $json->length . "', '" . $json->releaseyear . "');";
                $results = mysqli_query($connect, $query);
                http_response_code(201);
            } else {
                http_response_code(415);
            }
        } else if ($content == "application/x-www-form-urlencoded") {
            if (isset($_POST['title'], $_POST['synopsis'], $_POST['director'], $_POST['genre'], $_POST['length'], $_POST['releaseyear'])) {
                $query = "INSERT INTO `movies` (title, synopsis, director, genre, length, releaseyear) VALUES ('" . $_POST["title"] . "', '" . $_POST["synopsis"] . "', '" . $_POST["director"] . "', '" . $_POST["genre"] . "', '" . $_POST["length"] . "', '" . $_POST["releaseyear"] . "');";
                $results = mysqli_query($connect, $query);
                http_response_code(201);
            } else {
                http_response_code(415);
            }
        } else {
            http_response_code(415);
        }

        break;
    }

    case "PUT": {
        if ($_SERVER["CONTENT_TYPE"] == "application/json") {
            if ($id) {
                $body = file_get_contents("php://input");
                $json = json_decode($body);
                $incomplete = false;
                foreach ($json as $entry) {
                    if (!isset($entry) || empty($entry)) {
                        http_response_code(400);
                        exit;
                    }
                }
                $query = "SELECT `id` FROM `movies` WHERE `id` = " . $id;
                $results = mysqli_query($connect, $query);
                $value = mysqli_fetch_object($results);
                if ($value->id > 0) {
                    $query = "UPDATE `movies` SET `title` = '" . $json->title . "', `synopsis` = '" . $json->synopsis . "', `director` = '" . $json->director . "', `genre` = '" . $json->genre . "', `length` = '" . $json->length . "', `releaseyear` = '" . $json->releaseyear . "';";
                    $results = mysqli_query($connect, $query);
                    echo $query;
                } else {
                    http_response_code(404);
                }
            } else {
                http_response_code(405);
            }
        } else {
            http_response_code(415);
        }
        break;
    }

    case "DELETE": {
        if ($id) {
            $query = "DELETE FROM `movies` WHERE `id` = " . $id;
            $results = mysqli_query($connect, $query);
            http_response_code(204);
        } else {
            http_response_code(404);
        }
        break;
    }
}

function fillData($resultArray, $accept, $singleResult, $recordNumber, $rowStart, $limit)
{
    if ($accept == "application/json") {
        header("Content-Type: application/json");

        /**
         * Haalt collection
         * */
        if ($singleResult == false) {
            $jsonArray = [
                "items" => [],
            ];

            for ($i = 0; $i < count($resultArray); $i++) {
                $jsonArray["items"][$i] = $resultArray[$i];
                $jsonArray["items"][$i]["links"] = [
                    [
                        "rel" => "self",
                        "href" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays/" . $resultArray[$i]["id"]
                    ],
                    [
                        "rel" => "collection",
                        "href" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays"
                    ]
                ];
            }

            $jsonArray["links"][] = [
                "rel" => "self",
                "href" => "https://stud.hosted.hr.nl" . $_SERVER["REQUEST_URI"]
            ];


            $last = $recordNumber - ($recordNumber % $limit);
            $previous = $rowStart - $limit;
            if ($previous < 1) {
                $previous = 1;
            }
            $next = $rowStart + $limit;
            if ($next >= $recordNumber) {
                $next = $last;
            }

            if (isset($_GET['limit'])) {
                $linkData = [
                    "first" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays/?start=1&limit=" . $limit,
                    "last" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays/?start=" . $last . "&limit=" . $limit,
                    "previous" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays/?start=" . $previous . "&limit=" . $limit,
                    "next" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays/?start=" . $next . "&limit=" . $limit,
                ];
            } else {
                $linkData = [
                    "first" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays",
                    "last" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays",
                    "previous" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays",
                    "next" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays",
                ];
            }
            $modulus = ($rowStart % $limit);
            $currentPage = ($rowStart - $modulus) / $limit + 1;
            $totalPages = (int)ceil($recordNumber / $limit);
            $previousPage = $currentPage - 1;
            if ($currentPage < 1 || !isset($_GET['limit'])) {
                $previousPage = 1;
            };

            $nextPage = $currentPage + 1;
            if ($currentPage >= $totalPages) {
                $nextPage = $totalPages;
            }

            $jsonArray["pagination"] = [
                "currentPage" => $currentPage,
                "currentItems" => (int)count($resultArray),
                "totalPages" => $totalPages,
                "totalItems" => (int)$recordNumber,
                "links" => [
                    [
                        "rel" => "first",
                        "page" => 1,
                        "href" => $linkData["first"]
                    ],
                    [
                        "rel" => "last",
                        "page" => (int)ceil($recordNumber / $limit),
                        "href" => $linkData["last"]
                    ],
                    [
                        "rel" => "previous",
                        "page" => $previousPage,
                        "href" => $linkData["previous"]
                    ],
                    [
                        "rel" => "next",
                        "page" => $nextPage,
                        "href" => $linkData["next"]
                    ]
                ]
            ];
            /**
             * Haalt detail op
             */
        } else {
            $jsonArray["id"] = $resultArray[0]["id"];
            $jsonArray["title"] = $resultArray[0]["title"];
            $jsonArray["synopsis"] = $resultArray[0]["synopsis"];
            $jsonArray["director"] = $resultArray[0]["director"];
            $jsonArray["genre"] = $resultArray[0]["genre"];
            $jsonArray["length"] = $resultArray[0]["length"];
            $jsonArray["releaseyear"] = $resultArray[0]["releaseyear"];
            $jsonArray["links"] = [
                [
                    "rel" => "self",
                    "href" => "https://stud.hosted.hr.nl" . $_SERVER["REQUEST_URI"]
                ],
                [
                    "rel" => "collection",
                    "href" => "https://stud.hosted.hr.nl/0860995/jaar2/restful/bluraycollection/blurays/"
                ]
            ];
        }
        echo json_encode($jsonArray);
    } else if ($accept == "application/xml") {
        header("Content-Type: application/xml");
        echo "<movies>";
        echo "<items>";
        foreach ($resultArray as $result) {
            echo "<item>";
            echo "<id>$result[id]</id>";
            echo "<title>$result[title]</title>";
            echo "<synopsis>$result[synopsis]</synopsis>";
            echo "<director>$result[director]</director>";
            echo "<genre>$result[genre]</genre>";
            echo "<length>$result[length]</length>";
            echo "<releaseyear>$result[releaseyear]</releaseyear>";
            echo "</item>";
        }
        echo "</items>";
        echo "</movies>";

    }
}