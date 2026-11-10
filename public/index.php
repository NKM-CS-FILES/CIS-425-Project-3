<?php
session_start();
header("Content-Type: text/html; charset=utf-8");

$file = __DIR__ . "/../data/tasks.json";
$priorities = array("Low", "Medium", "High");

$tasks = array();
if (file_exists($file)) {
    $tasks = json_decode(file_get_contents($file), true);
}

$errors = array();
$title = "";
$description = "";
$priority = "Medium";
$due = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST["action"];

    if ($action == "create") {
        $title = trim($_POST["title"]);
        $description = trim($_POST["description"]);
        $priority = $_POST["priority"];
        $due = trim($_POST["due"]);

        if ($title == "") {
            $errors["title"] = "Title is required";
        } else if (strlen($title) > 100) {
            $errors["title"] = "Title is too long (100 max)";
        }
        if (strlen($description) > 500) {
            $errors["description"] = "Description is too long (500 max)";
        }
        if (!in_array($priority, $priorities)) {
            $errors["priority"] = "Pick a priority";
        }
        if ($due != "") {
            $parts = explode("-", $due);
            if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $due) || !checkdate($parts[1], $parts[2], $parts[0])) {
                $errors["due"] = "Not a valid date";
            }
        }

        if (count($errors) == 0) {
            $tasks[] = array(
                "id" => uniqid(),
                "title" => $title,
                "description" => $description,
                "priority" => $priority,
                "due" => $due == "" ? null : $due,
                "completed" => false
            );
            $_SESSION["flash"] = "Task added";
        }
    }

    if ($action == "complete" || $action == "delete") {
        foreach ($tasks as $i => $task) {
            if ($task["id"] == $_POST["id"]) {
                if ($action == "complete") {
                    $tasks[$i]["completed"] = true;
                } else {
                    unset($tasks[$i]);
                }
            }
        }
        $tasks = array_values($tasks);
        $_SESSION["flash"] = $action == "complete" ? "Task completed" : "Task deleted";
    }

    if (count($errors) == 0) {
        if (!file_exists(__DIR__ . "/../data")) {
            mkdir(__DIR__ . "/../data");
        }
        file_put_contents($file, json_encode($tasks));
        header("Location: index.php");
        exit;
    }
}

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$filter = isset($_GET["priority"]) ? $_GET["priority"] : "";

$flash = "";
if (isset($_SESSION["flash"])) {
    $flash = $_SESSION["flash"];
    unset($_SESSION["flash"]);
}

$shown = array();
$done = 0;
foreach ($tasks as $task) {
    if ($task["completed"]) {
        $done++;
    }
    if ($q != "" && stripos($task["title"], $q) === false && stripos($task["description"], $q) === false) {
        continue;
    }
    if (in_array($filter, $priorities) && $task["priority"] != $filter) {
        continue;
    }
    $shown[] = $task;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TaskPad PHP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 700px;">

<h1 class="mb-4">TaskPad PHP</h1>

<?php if ($flash != "") { ?>
    <div class="alert alert-success"><?php echo $flash; ?></div>
<?php } ?>

<div class="card mb-4">
<div class="card-body">
    <h2 class="h4">Add Task</h2>
    <form method="post">
        <input type="hidden" name="action" value="create">

        <div class="mb-3">
            <input type="text" name="title" placeholder="Title *" class="form-control <?php if (isset($errors["title"])) echo "is-invalid"; ?>" value="<?php echo htmlspecialchars($title); ?>">
            <?php if (isset($errors["title"])) { ?><div class="invalid-feedback"><?php echo $errors["title"]; ?></div><?php } ?>
        </div>

        <div class="mb-3">
            <textarea name="description" rows="2" placeholder="Description" class="form-control <?php if (isset($errors["description"])) echo "is-invalid"; ?>"><?php echo htmlspecialchars($description); ?></textarea>
            <?php if (isset($errors["description"])) { ?><div class="invalid-feedback"><?php echo $errors["description"]; ?></div><?php } ?>
        </div>

        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select <?php if (isset($errors["priority"])) echo "is-invalid"; ?>">
                    <?php foreach ($priorities as $p) { ?>
                        <option <?php if ($p == $priority) echo "selected"; ?>><?php echo $p; ?></option>
                    <?php } ?>
                </select>
                <?php if (isset($errors["priority"])) { ?><div class="invalid-feedback"><?php echo $errors["priority"]; ?></div><?php } ?>
            </div>
            <div class="col-sm-6 mb-3">
                <label class="form-label">Due date</label>
                <input type="date" name="due" class="form-control <?php if (isset($errors["due"])) echo "is-invalid"; ?>" value="<?php echo htmlspecialchars($due); ?>">
                <?php if (isset($errors["due"])) { ?><div class="invalid-feedback"><?php echo $errors["due"]; ?></div><?php } ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Add Task</button>
    </form>
</div>
</div>

<h2 class="h4">Tasks</h2>
<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm">
        <input type="text" name="q" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="col col-sm-auto">
        <select name="priority" class="form-select">
            <option value="">All</option>
            <?php foreach ($priorities as $p) { ?>
                <option <?php if ($p == $filter) echo "selected"; ?>><?php echo $p; ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="index.php" class="btn btn-outline-secondary">Clear</a>
    </div>
</form>

<p class="text-muted">
    Total: <?php echo count($tasks); ?> |
    Open: <?php echo count($tasks) - $done; ?> |
    Completed: <?php echo $done; ?> |
    Showing: <?php echo count($shown); ?>
</p>

<?php if (count($tasks) == 0) { ?>
    <div class="alert alert-secondary">No tasks yet</div>
<?php } else if (count($shown) == 0) { ?>
    <div class="alert alert-secondary">No tasks match your search</div>
<?php } ?>

<?php foreach ($shown as $task) { ?>
    <div class="card mb-2">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong class="<?php echo $task["completed"] ? "text-decoration-line-through text-muted" : ""; ?>"><?php echo htmlspecialchars($task["title"]); ?></strong>
            <span class="badge text-bg-secondary"><?php echo $task["priority"]; ?></span>
            <span class="badge <?php echo $task["completed"] ? "text-bg-success" : "text-bg-light border"; ?>"><?php echo $task["completed"] ? "Completed" : "Open"; ?></span>

            <?php if ($task["due"] != null) { ?>
                <div class="small text-muted">Due: <?php echo $task["due"]; ?></div>
            <?php } ?>
            <?php if ($task["description"] != "") { ?>
                <div><?php echo htmlspecialchars($task["description"]); ?></div>
            <?php } ?>
        </div>

        <form method="post">
            <input type="hidden" name="id" value="<?php echo $task["id"]; ?>">
            <?php if (!$task["completed"]) { ?>
                <button type="submit" name="action" value="complete" class="btn btn-sm btn-outline-success">Complete</button>
            <?php } ?>
            <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button>
        </form>
    </div>
    </div>
<?php } ?>

</div>
</body>
</html>