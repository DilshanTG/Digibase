
import os

# Configuration
PROJECT_ROOT = '/Applications/XAMPP/xamppfiles/htdocs/digibase'
OUTPUT_FILE = os.path.join(PROJECT_ROOT, 'DIGIBASE_FULL_SOURCE.md')

# Directories and files to include
INCLUDE_PATHS = [
    'app',
    'routes',
    'config',
    'database/migrations',
    'resources/views/filament',
    'bootstrap/app.php',
    'composer.json',
    'PHASE2_SUMMARY.md',
    'CORE_API_ARCHITECTURE.md',
    'README.md'
]

# Extensions to include
ALLOWED_EXTENSIONS = {'.php', '.json', '.md', '.blade.php', '.js', '.css'}

# Files/Dirs to ignore explicitly (even if in include paths)
IGNORE_LIST = {
    'vendor', 'node_modules', 'storage', 'bootstrap/cache', '.git', 'public/vendor'
}

def should_include(path):
    # Check if path is in ignore list
    for ignore in IGNORE_LIST:
        if path.startswith(os.path.join(PROJECT_ROOT, ignore)):
            return False
            
    # Check extension
    _, ext = os.path.splitext(path)
    if ext == '.php' and path.endswith('.blade.php'):
        return True # Handle double extension
    return ext in ALLOWED_EXTENSIONS

def generate_dump():
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        f.write("# Digibase - Full Source Code Dump\n")
        f.write(f"Generated at: {os.popen('date').read().strip()}\n\n")
        
        for base_path in INCLUDE_PATHS:
            abs_base = os.path.join(PROJECT_ROOT, base_path)
            
            if not os.path.exists(abs_base):
                continue
                
            if os.path.isfile(abs_base):
                files_to_process = [abs_base]
            else:
                files_to_process = []
                for root, _, filenames in os.walk(abs_base):
                    for filename in filenames:
                        files_to_process.append(os.path.join(root, filename))
            
            for file_path in sorted(files_to_process):
                if not should_include(file_path):
                    continue
                    
                rel_path = os.path.relpath(file_path, PROJECT_ROOT)
                
                try:
                    with open(file_path, 'r', encoding='utf-8') as src:
                        content = src.read()
                        
                    f.write(f"## File: {rel_path}\n")
                    
                    # Determine markdown language
                    lang = 'php'
                    if rel_path.endswith('.json'): lang = 'json'
                    elif rel_path.endswith('.md'): lang = 'markdown'
                    elif rel_path.endswith('.js'): lang = 'javascript'
                    elif rel_path.endswith('.css'): lang = 'css'
                    
                    f.write(f"```{lang}\n")
                    f.write(content)
                    f.write("\n```\n\n")
                    
                except Exception as e:
                    f.write(f"## File: {rel_path}\n")
                    f.write(f"Error reading file: {str(e)}\n\n")

    print(f"Dump completed: {OUTPUT_FILE}")

if __name__ == "__main__":
    generate_dump()
