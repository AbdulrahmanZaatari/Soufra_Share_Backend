<?php
require 'connection.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getReview($_GET['id']);
        } elseif (isset($_GET['reviewer_id'])) {
            getReviewsByReviewer($_GET['reviewer_id']);
        } elseif (isset($_GET['reviewee_id'])) {
            getReviewsByReviewee($_GET['reviewee_id']);
        } else {
            getReviews();
        }
        break;
    case 'POST':
        createReview();
        break;
    case 'PUT':
        updateReview();
        break;
    case 'DELETE':
        deleteReview();
        break;
    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method not allowed']);
        break;
}

function getReviews() {
    global $conn;
    $sql = "SELECT * FROM Reviews";
    $result = $conn->query($sql);
    $reviews = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
    echo json_encode($reviews);
}

function getReviewsByReviewer($reviewer_id) {
    global $conn;
    $sql = "SELECT * FROM Reviews WHERE reviewer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reviewer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
    echo json_encode($reviews);
}

function getReviewsByReviewee($reviewee_id) {
    global $conn;
    $sql = "SELECT r.review_id, r.reviewer_id, r.reviewee_id, r.rating, r.comment, r.review_date, u.username AS reviewer_username
            FROM Reviews r
            INNER JOIN Users u ON r.reviewer_id = u.user_id
            WHERE r.reviewee_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reviewee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
    echo json_encode($reviews);
}

function getReview($id) {
    global $conn;
    $sql = "SELECT * FROM Reviews WHERE review_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        echo json_encode($result->fetch_assoc());
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Review not found']);
    }
}

function createReview() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $reviewer_id = $data->reviewer_id;
    $reviewee_id = $data->reviewee_id;
    $rating = $data->rating;
    $comment = $data->comment;
    $review_date = date('Y-m-d H:i:s');

    $sql = "INSERT INTO Reviews (reviewer_id, reviewee_id, rating, comment, review_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiss", $reviewer_id, $reviewee_id, $rating, $comment, $review_date);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(['message' => 'Review created successfully', 'review_id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error creating review: ' . $stmt->error]);
    }
}

function updateReview() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $review_id = $data->review_id;
    $reviewer_id = $data->reviewer_id ?? null;
    $reviewee_id = $data->reviewee_id ?? null;
    $rating = $data->rating ?? null;
    $comment = $data->comment ?? null;
    $review_date = $data->review_date ?? null;

    $sql = "UPDATE Reviews SET reviewer_id=?, reviewee_id=?, rating=?, comment=?, review_date=? WHERE review_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiissi", $reviewer_id, $reviewee_id, $rating, $comment, $review_date, $review_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Review updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error updating review: ' . $stmt->error]);
    }
}

function deleteReview() {
    global $conn;
    $data = json_decode(file_get_contents("php://input"));
    $review_id = $data->review_id;

    $sql = "DELETE FROM Reviews WHERE review_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $review_id);

    if ($stmt->execute()) {
        echo json_encode(['message' => 'Review deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Error deleting review: ' . $stmt->error]);
    }
}

$conn->close();
?>