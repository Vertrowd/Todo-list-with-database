<?php
// to-do.php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database configuration (same as login.php)
$host = 'localhost';
$dbname = 'user_auth';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    try {
        switch ($action) {
            case 'add_task':
                $task = trim($_POST['task'] ?? '');
                if (empty($task)) {
                    throw new Exception('Task cannot be empty.');
                }
                
                $stmt = $pdo->prepare("INSERT INTO tasks (user_id, task, created_at) VALUES (?, ?, NOW())");
                if ($stmt->execute([$user_id, $task])) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Task added successfully!',
                        'task_id' => $pdo->lastInsertId()
                    ]);
                } else {
                    throw new Exception('Failed to add task to database.');
                }
                break;
                
            case 'delete_task':
                $task_id = $_POST['task_id'] ?? '';
                if (empty($task_id) || !is_numeric($task_id)) {
                    throw new Exception('Valid Task ID is required.');
                }
                
                // Verify task belongs to user before deleting
                $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
                $stmt->execute([$task_id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Task deleted successfully!'
                    ]);
                } else {
                    throw new Exception('Task not found or you do not have permission to delete it.');
                }
                break;
                
            case 'toggle_task':
                $task_id = $_POST['task_id'] ?? '';
                $completed = $_POST['completed'] ?? 0;
                
                if (empty($task_id) || !is_numeric($task_id)) {
                    throw new Exception('Valid Task ID is required.');
                }
                
                $stmt = $pdo->prepare("UPDATE tasks SET completed = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$completed, $task_id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Task updated successfully!'
                    ]);
                } else {
                    throw new Exception('Task not found or you do not have permission to update it.');
                }
                break;
                
            default:
                throw new Exception('Invalid action.');
        }
    } catch (Exception $e) {
        error_log("Todo List Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Fetch user's tasks
try {
    $stmt = $pdo->prepare("SELECT id, task, completed, created_at FROM tasks WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $tasks = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo List</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            margin-bottom: 10px;
            font-size: 2.5em;
        }

        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: white;
            color: #667eea;
        }

        .content {
            padding: 30px;
        }

        .add-task-form {
            display: flex;
            margin-bottom: 30px;
            gap: 10px;
        }

        .task-input {
            flex: 1;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }

        .task-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .add-btn {
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .add-btn:hover {
            transform: translateY(-2px);
        }

        .add-btn:disabled {
            background: #ccc;
            transform: none;
            cursor: not-allowed;
        }

        .tasks-section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        .task-list {
            list-style: none;
        }

        .task-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }

        .task-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .task-item.completed {
            opacity: 0.7;
            border-left-color: #28a745;
        }

        .task-checkbox {
            margin-right: 15px;
            transform: scale(1.2);
            cursor: pointer;
        }

        .task-text {
            flex: 1;
            font-size: 16px;
        }

        .task-text.completed {
            text-decoration: line-through;
            color: #6c757d;
        }

        .task-date {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .no-tasks {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 40px;
        }

        .message {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.hidden {
            display: none;
        }

        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
            text-align: center;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            flex: 1;
            margin: 0 10px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 My Todo List</h1>
            <p>Manage your tasks efficiently</p>
            <div class="user-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</span>
                <a href="login.php?logout=1" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="content">
            <div id="messageDiv" class="message hidden"></div>

            <!-- Statistics -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($tasks); ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php 
                        $completed_count = 0;
                        foreach ($tasks as $task) {
                            if ($task['completed'] == 1) $completed_count++;
                        }
                        echo $completed_count;
                        ?>
                    </div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo count($tasks) - $completed_count; ?>
                    </div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>

            <!-- Add Task Form -->
            <form id="addTaskForm" class="add-task-form">
                <input type="text" id="taskInput" class="task-input" placeholder="Enter a new task..." required>
                <button type="submit" id="addBtn" class="add-btn">Add Task</button>
            </form>

            <!-- Tasks List -->
            <div class="tasks-section">
                <h2>Your Tasks</h2>
                <ul id="taskList" class="task-list">
                    <?php if (empty($tasks)): ?>
                        <li class="no-tasks">No tasks yet. Add your first task above!</li>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <li class="task-item <?php echo $task['completed'] ? 'completed' : ''; ?>" data-task-id="<?php echo $task['id']; ?>">
                                <input type="checkbox" class="task-checkbox" <?php echo $task['completed'] ? 'checked' : ''; ?>>
                                <div class="task-text <?php echo $task['completed'] ? 'completed' : ''; ?>">
                                    <?php echo htmlspecialchars($task['task']); ?>
                                    <div class="task-date">
                                        Added: <?php echo date('M j, Y g:i A', strtotime($task['created_at'])); ?>
                                    </div>
                                </div>
                                <button class="delete-btn" type="button">Delete</button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Add task
        document.getElementById('addTaskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const taskInput = document.getElementById('taskInput');
            const task = taskInput.value.trim();
            const addBtn = document.getElementById('addBtn');
            
            if (!task) {
                showMessage('Please enter a task!', 'error');
                return;
            }
            
            addBtn.disabled = true;
            addBtn.textContent = 'Adding...';
            
            const formData = new FormData();
            formData.append('action', 'add_task');
            formData.append('task', task);
            
            fetch('to-do.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    taskInput.value = '';
                    addTaskToList(task, data.task_id);
                    updateStats();
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            })
            .finally(() => {
                addBtn.disabled = false;
                addBtn.textContent = 'Add Task';
            });
        });

        // Delete task
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('delete-btn')) {
                const taskItem = e.target.closest('.task-item');
                const taskId = taskItem.dataset.taskId;
                
                if (confirm('Are you sure you want to delete this task?')) {
                    deleteTask(taskId, taskItem);
                }
            }
        });

        // Toggle task completion
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('task-checkbox')) {
                const taskItem = e.target.closest('.task-item');
                const taskId = taskItem.dataset.taskId;
                const completed = e.target.checked ? 1 : 0;
                
                toggleTask(taskId, completed, taskItem);
            }
        });

        function addTaskToList(task, taskId) {
            const taskList = document.getElementById('taskList');
            const noTasks = taskList.querySelector('.no-tasks');
            
            if (noTasks) {
                noTasks.remove();
            }
            
            const taskItem = document.createElement('li');
            taskItem.className = 'task-item';
            taskItem.dataset.taskId = taskId;
            taskItem.innerHTML = `
                <input type="checkbox" class="task-checkbox">
                <div class="task-text">
                    ${escapeHtml(task)}
                    <div class="task-date">
                        Added: ${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })}
                    </div>
                </div>
                <button class="delete-btn" type="button">Delete</button>
            `;
            
            taskList.insertBefore(taskItem, taskList.firstChild);
        }

        function deleteTask(taskId, taskItem) {
            const formData = new FormData();
            formData.append('action', 'delete_task');
            formData.append('task_id', taskId);
            
            fetch('to-do.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    taskItem.remove();
                    updateStats();
                    
                    // Show "no tasks" message if all tasks are deleted
                    const taskList = document.getElementById('taskList');
                    if (taskList.children.length === 0) {
                        const noTasks = document.createElement('li');
                        noTasks.className = 'no-tasks';
                        noTasks.textContent = 'No tasks yet. Add your first task above!';
                        taskList.appendChild(noTasks);
                    }
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        }

        function toggleTask(taskId, completed, taskItem) {
            const formData = new FormData();
            formData.append('action', 'toggle_task');
            formData.append('task_id', taskId);
            formData.append('completed', completed);
            
            fetch('to-do.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (completed) {
                        taskItem.classList.add('completed');
                        taskItem.querySelector('.task-text').classList.add('completed');
                    } else {
                        taskItem.classList.remove('completed');
                        taskItem.querySelector('.task-text').classList.remove('completed');
                    }
                    updateStats();
                } else {
                    showMessage(data.message, 'error');
                    // Revert checkbox state
                    taskItem.querySelector('.task-checkbox').checked = !completed;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
                // Revert checkbox state
                taskItem.querySelector('.task-checkbox').checked = !completed;
            });
        }

        function updateStats() {
            // Simple page reload to update stats
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        function showMessage(message, type) {
            const messageDiv = document.getElementById('messageDiv');
            messageDiv.textContent = message;
            messageDiv.className = `message ${type}`;
            messageDiv.classList.remove('hidden');
            
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 5000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>