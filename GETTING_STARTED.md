# Getting Started with Claude Code

Welcome! This guide will help you use Claude to write PHP code safely.

---

## Setting Up a New Project

### Step 1: Download the Files

Open Terminal (the black window icon) and type these commands one at a time:

```
cd ~/Projects
```
(This goes to your Projects folder. Change the path if yours is different.)

```
git clone https://github.com/YOUR-USERNAME/php-guardian.git my-new-project
```
(Change `my-new-project` to whatever you want to call your project)

```
cd my-new-project
```

### Step 2: Make It Your Own

```
rm -rf .git
```
(This removes the connection to the original)

```
git init
```
(This starts fresh for your project)

### Step 3: Install the Tools

```
composer install
```
(This installs PHP tools. You may need to wait a minute.)

```
pip install pre-commit
```
(This installs the safety checks.)

```
pre-commit install
```
(This turns on automatic checking.)

### Step 4: Create Your Source Folder

```
mkdir -p src
```
(This creates the folder where your code goes.)

### Done!

Now when you save code, the safety checks run automatically.

---

## Opening in VSCode

1. Open VSCode
2. Click **File** > **Open Folder**
3. Find and select your project folder
4. Click **Open**

The Claude sidebar should appear on the left.

---

## How to Talk to Claude

In the VSCode sidebar, you can type messages to Claude. Here are some helpful phrases:

---

## When Starting a New Feature

Copy and paste this:

```
I need to add [describe what you want].

Before you write any code:
1. Tell me what files you'll create or change
2. Explain your approach in simple terms
3. Wait for my OK before writing code
```

---

## When Claude Writes Code

After Claude writes code, ask:

```
Before I use this code, check it for:
- SQL injection (are you using prepared statements?)
- XSS (are you escaping output?)
- Any passwords or secrets in the code?
- Any TODO or placeholder data?
```

---

## When Something Doesn't Work

Copy and paste this:

```
This isn't working. The error message is:

[paste the error here]

Please:
1. Explain what the error means in simple terms
2. Show me exactly what to change
3. Explain why this fixes it
```

---

## When You're Not Sure What Claude Did

Ask:

```
Explain what this code does line by line.
Use simple words, not technical jargon.
```

---

## Before Going Live

Ask Claude:

```
Review all the code you wrote today.
Check for:
- Security problems
- Fake or test data that needs to be replaced
- Debug code that should be removed
- Anything that's not finished
```

---

## Simple Commands

| You Want To... | Say This |
|----------------|----------|
| Create a new page | "Create a page that shows [what]" |
| Add a form | "Add a form that collects [what fields]" |
| Save to database | "Save this to the database" |
| Show data from database | "Show all [things] from the database" |
| Add login | "Add a login system" |
| Fix a bug | "This isn't working: [describe problem]" |
| Understand code | "Explain this code simply" |

---

## Safety Reminders

Always tell Claude:

```
Remember:
- Use PDO with prepared statements for database
- Escape all output with htmlspecialchars()
- No fake data - use real values or environment variables
- No var_dump or die() - use proper error handling
```

---

## If Claude Uses Words You Don't Understand

Just ask:

```
What does [word] mean? Explain it simply.
```

Claude will explain in plain English.

---

## Getting Help

If you're stuck, try:

```
I'm trying to [goal].
I've done [what you've done].
I'm stuck on [the problem].
What should I do next?
```

Claude will guide you step by step.
