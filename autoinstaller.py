#!/usr/bin/python3
import subprocess
import sys
import pathlib
import os

# Global variables
suite_scripts = ['autoscp', 'autosql', 'autossh', 'autotunnel', 'get_autosuite']
home_path = ''
sys_python_binary = ''
virtualenv_python_binary = ''


def is_admin():
    args = '[bool](([System.Security.Principal.WindowsIdentity]::GetCurrent()).groups -match "S-1-5-32-544")'
    if subprocess.run(['powershell', '-Command', args], capture_output=True, text=True).stdout.split('\n')[0] == \
            'True':
        return True
    return False


def set_home_path():
    global home_path
    if os.name == 'nt':
        home_path = str(os.environ['USERPROFILE'])
    else:
        home_path = str(os.environ['HOME'])


def set_sys_python_binary():
    global sys_python_binary
    if os.name == 'nt':
        sys_python_binary = 'python'
    else:
        sys_python_binary = 'python3'


def set_virtualenv_python_binary():
    global virtualenv_python_binary
    if os.name == 'nt':
        virtualenv_python_binary = home_path + '/.autosuite/autoenv/Scripts/python.exe'
    else:
        virtualenv_python_binary = home_path + '/.autosuite/autoenv/bin/python3'


def set_global_variables():
    set_home_path()
    set_virtualenv_python_binary()
    set_sys_python_binary()


def check_local_dir():
    local_dir_prompt = ''
    if os.name != 'nt' and not os.path.isdir(home_path + '/.local'):
        local_dir_prompt += 'The directory ~./local was just created. Please log out and log back in for it to be ' \
                            'added to your PATH or run the following command in your current terminal session before ' \
                            'using the autosuite scripts:\nsource ~/.profile'
    return local_dir_prompt


# Checking the version of Python on the system.
def check_version():
    if sys.version_info.major < 3 or sys.version_info.minor < 7:
        print(f'Minimum Python version required: 3.7. Found: {sys.version_info.major}.{sys.version_info.minor}.')
        sys.exit(1)


def pip_install(python_binary):
    args = [python_binary, '-m', 'pip', 'install', '--upgrade', 'pip']
    subprocess.run(args)


def virtualenv_install():
    args = [sys_python_binary, '-m', 'pip', 'install', '--upgrade', 'virtualenv']
    subprocess.run(args)


def autoenv_create():
    autosuite_directory = home_path + '/.autosuite/'
    pathlib.Path(autosuite_directory).mkdir(exist_ok=True)
    os.chdir(autosuite_directory)
    args = [home_path + '/.local/bin/virtualenv', 'autoenv']
    subprocess.run(args)


def keyring_install():
    args = [virtualenv_python_binary, '-m', 'pip', 'install', '--upgrade', '--no-warn-script-location', 'keyring']
    subprocess.run(args)


def configure_pypi_index_url():
    args = [virtualenv_python_binary, '-m', 'pip', 'config', 'set', '--site', 'global.extra-index-url',
            'https://pypi.schoolbox.com.au/simple']
    subprocess.run(args)


def autossh_suite_install():
    args = [virtualenv_python_binary, '-m', 'pip', 'install', '--upgrade', '--no-warn-script-location', 'autosuite']
    subprocess.run(args)


def symlink():
    if os.name == 'nt':
        path_to_symlinks = ''
        # Finding the location of symlinks for Python scripts via the PATH variable
        paths = os.environ['PATH'].split(';')
        for path in paths:
            if path.endswith('Scripts\\') and 'Python' in path:
                path_to_symlinks = path
        path_to_scripts = home_path + '/.autosuite/autoenv/Scripts/'
        for script in suite_scripts:
            try:
                os.unlink(path_to_symlinks + script + '.exe')
            except FileNotFoundError:
                pass
            os.symlink(path_to_scripts + script + '.exe', path_to_symlinks + script + '.exe')
    else:
        path_to_symlinks = home_path + '/.local/bin/'
        path_to_scripts = home_path + '/.autosuite/autoenv/bin/'
        for script in suite_scripts:
            try:
                os.unlink(path_to_symlinks + script)
            except FileNotFoundError:
                pass
            os.symlink(path_to_scripts + script, path_to_symlinks + script)


def main():
    if os.name == 'nt' and not is_admin():
        print('Please run PowerShell as administrator.')
        sys.exit(1)
    check_version()
    set_global_variables()
    local_dir_prompt = check_local_dir()
    pip_install(sys_python_binary)
    virtualenv_install()
    autoenv_create()
    pip_install(virtualenv_python_binary)
    keyring_install()
    configure_pypi_index_url()
    autossh_suite_install()
    symlink()
    print(local_dir_prompt)


if __name__ == '__main__':
    try:
        main()
    except (KeyboardInterrupt, EOFError):
        sys.exit('\nExiting...')
