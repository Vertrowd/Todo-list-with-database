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
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #87CEEB 0%, #FFB6C1 100%);
        min-height: 100vh;
        padding: 20px;
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(135, 206, 235, 0.3);
        overflow: hidden;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.5);
    }

    .header {
        background: linear-gradient(135deg, #87CEEB 0%, #FF69B4 100%);
        color: white;
        padding: 30px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.3;
    }

    .header h1 {
        margin-bottom: 10px;
        font-size: 2.5em;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        position: relative;
    }

    .user-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
        position: relative;
    }

    .logout-btn {
        background: rgba(255,255,255,0.3);
        color: white;
        border: 2px solid white;
        padding: 8px 20px;
        border-radius: 25px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s;
        backdrop-filter: blur(10px);
    }

    .logout-btn:hover {
        background: white;
        color: #FF69B4;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        padding: 15px 20px;
        border: 2px solid #87CEEB;
        border-radius: 12px;
        font-size: 16px;
        background: rgba(255, 255, 255, 0.9);
        transition: all 0.3s;
    }

    .task-input:focus {
        outline: none;
        border-color: #FF69B4;
        box-shadow: 0 0 0 3px rgba(255, 105, 180, 0.2);
        background: white;
    }

    .add-btn {
        padding: 15px 30px;
        background: linear-gradient(135deg, #87CEEB 0%, #FF69B4 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(255, 105, 180, 0.3);
    }

    .add-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(255, 105, 180, 0.4);
    }

    .add-btn:active {
        transform: translateY(-1px);
    }

    .add-btn:disabled {
        background: #ccc;
        transform: none;
        cursor: not-allowed;
        box-shadow: none;
    }

    .tasks-section h2 {
        margin-bottom: 20px;
        color: #2c3e50;
        border-bottom: 3px solid #87CEEB;
        padding-bottom: 10px;
        font-weight: 600;
    }

    .task-list {
        list-style: none;
    }

    .task-item {
        display: flex;
        align-items: center;
        padding: 18px;
        background: linear-gradient(135deg, #f8fbff 0%, #fff5f8 100%);
        border-radius: 12px;
        margin-bottom: 12px;
        border-left: 5px solid #87CEEB;
        transition: all 0.3s;
        box-shadow: 0 2px 10px rgba(135, 206, 235, 0.1);
    }

    .task-item:hover {
        background: linear-gradient(135deg, #e3f2fd 0%, #ffeef6 100%);
        transform: translateX(8px);
        box-shadow: 0 5px 15px rgba(135, 206, 235, 0.2);
    }

    .task-item.completed {
        opacity: 0.8;
        border-left-color: #FF69B4;
        background: linear-gradient(135deg, #f0f8ff 0%, #fff0f5 100%);
    }

    .task-checkbox {
        margin-right: 18px;
        transform: scale(1.3);
        cursor: pointer;
        accent-color: #FF69B4;
    }

    .task-text {
        flex: 1;
        font-size: 16px;
        color: #2c3e50;
        font-weight: 500;
    }

    .task-text.completed {
        text-decoration: line-through;
        color: #7f8c8d;
    }

    .task-date {
        font-size: 12px;
        color: #87CEEB;
        margin-top: 5px;
        font-weight: 500;
    }

    .delete-btn {
        background: linear-gradient(135deg, #FF69B4 0%, #ff8cba 100%);
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        box-shadow: 0 2px 8px rgba(255, 105, 180, 0.3);
    }

    .delete-btn:hover {
        background: linear-gradient(135deg, #ff4da6 0%, #ff69b4 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 105, 180, 0.4);
    }

    .no-tasks {
        text-align: center;
        color: #87CEEB;
        font-style: italic;
        padding: 40px;
        font-size: 18px;
        background: rgba(135, 206, 235, 0.1);
        border-radius: 12px;
        border: 2px dashed #87CEEB;
    }

    .message {
        margin: 20px 0;
        padding: 15px 20px;
        border-radius: 12px;
        text-align: center;
        font-weight: bold;
        transition: all 0.3s;
    }

    .message.success {
        background: linear-gradient(135deg, #d4edda 0%, #e8f5e8 100%);
        color: #155724;
        border: 2px solid #87CEEB;
    }

    .message.error {
        background: linear-gradient(135deg, #f8d7da 0%, #ffe6e6 100%);
        color: #721c24;
        border: 2px solid #FF69B4;
    }

    .message.hidden {
        display: none;
    }

    .stats {
        display: flex;
        justify-content: space-around;
        margin-bottom: 30px;
        text-align: center;
        gap: 15px;
    }

    .stat-card {
        background: linear-gradient(135deg, #87CEEB 0%, #FFB6C1 100%);
        padding: 25px 15px;
        border-radius: 15px;
        flex: 1;
        box-shadow: 0 5px 15px rgba(135, 206, 235, 0.3);
        transition: all 0.3s;
        color: white;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(135, 206, 235, 0.4);
    }

    .stat-number {
        font-size: 2.2em;
        font-weight: bold;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    }

    .stat-label {
        margin-top: 8px;
        font-weight: 600;
        opacity: 0.9;
    }

    /* Floating animation for header */
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }

    .header h1 {
        animation: float 6s ease-in-out infinite;
    }

    /* Pulse animation for add button */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    .add-btn {
        animation: pulse 2s infinite;
    }

    .add-btn:hover {
        animation: none;
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