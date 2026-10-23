using System;
using System.IO;
using System.Diagnostics;
using System.Windows.Forms;
using System.Drawing;
using Microsoft.Win32;

namespace RacconMailDesktop
{
    static class Program
    {
        private const string AppName = "Raccon Mail";
        private const string AppVersion = "1.0.0";
        private const string AppUrl = "https://racconmail.eu/";
        private const string AppPublisher = "Raccon Mail Open Source";

        [STAThread]
        static void Main(string[] args)
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);

            string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
            string installDir = Path.Combine(localAppData, "Programs", "RacconMail");
            string targetExe = Path.Combine(installDir, "RacconMail.exe");
            string targetIco = Path.Combine(installDir, "raccoon.ico");

            // Check if running as uninstaller
            if (args.Length > 0 && args[0].Equals("--uninstall", StringComparison.OrdinalIgnoreCase))
            {
                Uninstall(installDir);
                return;
            }

            // Check if running from outside the install directory (i.e. running Setup)
            string currentExe = Application.ExecutablePath;
            bool isSetup = !currentExe.Equals(targetExe, StringComparison.OrdinalIgnoreCase);

            if (isSetup)
            {
                // Run Installation Flow
                RunInstaller(currentExe, installDir, targetExe, targetIco);
            }
            else
            {
                // Launch the Desktop App
                LaunchApp();
            }
        }

        static void RunInstaller(string sourceExe, string installDir, string targetExe, string targetIco)
        {
            try
            {
                if (!Directory.Exists(installDir))
                {
                    Directory.CreateDirectory(installDir);
                }

                // Copy executable to installation directory
                File.Copy(sourceExe, targetExe, true);

                // Copy or extract icon
                string sourceIco = Path.Combine(Path.GetDirectoryName(sourceExe), "raccoon.ico");
                if (File.Exists(sourceIco))
                {
                    File.Copy(sourceIco, targetIco, true);
                }

                // Create Desktop Shortcut
                string desktopPath = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                string desktopShortcut = Path.Combine(desktopPath, AppName + ".lnk");
                CreateShortcut(desktopShortcut, targetExe, targetIco, "Fast, Nocturnal & Masked Webmail");

                // Create Start Menu Shortcut
                string startMenuPath = Environment.GetFolderPath(Environment.SpecialFolder.Programs);
                string startMenuShortcut = Path.Combine(startMenuPath, AppName + ".lnk");
                CreateShortcut(startMenuShortcut, targetExe, targetIco, "Fast, Nocturnal & Masked Webmail");

                // Register in Windows Add/Remove Programs (Registry)
                RegisterUninstall(installDir, targetExe, targetIco);

                // Show success prompt and launch
                MessageBox.Show(
                    "🦝 Raccon Mail Desktop has been installed successfully!\n\n" +
                    "• Desktop shortcut created\n" +
                    "• Start Menu shortcut added\n\n" +
                    "Launching Raccon Mail now...",
                    "Raccon Mail Setup",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information
                );

                // Launch installed application
                Process.Start(new ProcessStartInfo
                {
                    FileName = targetExe,
                    UseShellExecute = true
                });
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "Error during installation: " + ex.Message,
                    "Raccon Mail Setup Error",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
            }
        }

        static void LaunchApp()
        {
            try
            {
                string browserPath = FindChromiumBrowser();
                if (string.IsNullOrEmpty(browserPath))
                {
                    // Fallback to default browser if neither Edge nor Chrome found
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = AppUrl,
                        UseShellExecute = true
                    });
                    return;
                }

                string localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
                string userDataDir = Path.Combine(localAppData, "RacconMail", "User Data");

                string appArgs = string.Format(
                    "--app=\"{0}\" --window-size=1280,850 --user-data-dir=\"{1}\" --enable-features=OverlayScrollbar --disable-features=Translate,TranslateUI --disable-translate --lang=en-US",
                    AppUrl,
                    userDataDir
                );

                Process.Start(new ProcessStartInfo
                {
                    FileName = browserPath,
                    Arguments = appArgs,
                    UseShellExecute = true
                });
            }
            catch (Exception ex)
            {
                MessageBox.Show(
                    "Could not launch application: " + ex.Message,
                    "Raccon Mail",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Error
                );
            }
        }

        static string FindChromiumBrowser()
        {
            // 1. Check Microsoft Edge (Default on Windows 10 & 11)
            string[] edgePaths = new string[]
            {
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), "Microsoft", "Edge", "Application", "msedge.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Microsoft", "Edge", "Application", "msedge.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Microsoft", "Edge", "Application", "msedge.exe")
            };

            foreach (string p in edgePaths)
            {
                if (File.Exists(p)) return p;
            }

            // 2. Check Google Chrome
            string[] chromePaths = new string[]
            {
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), "Google", "Chrome", "Application", "chrome.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), "Google", "Chrome", "Application", "chrome.exe"),
                Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "Google", "Chrome", "Application", "chrome.exe")
            };

            foreach (string p in chromePaths)
            {
                if (File.Exists(p)) return p;
            }

            return null;
        }

        static void CreateShortcut(string shortcutPath, string targetPath, string iconPath, string description)
        {
            try
            {
                Type shellType = Type.GetTypeFromProgID("WScript.Shell");
                if (shellType == null) return;

                dynamic shell = Activator.CreateInstance(shellType);
                dynamic shortcut = shell.CreateShortcut(shortcutPath);

                shortcut.TargetPath = targetPath;
                shortcut.WorkingDirectory = Path.GetDirectoryName(targetPath);
                shortcut.Description = description;

                if (File.Exists(iconPath))
                {
                    shortcut.IconLocation = iconPath + ",0";
                }

                shortcut.Save();
            }
            catch { }
        }

        static void RegisterUninstall(string installDir, string targetExe, string iconPath)
        {
            try
            {
                string keyPath = @"Software\Microsoft\Windows\CurrentVersion\Uninstall\RacconMail";
                using (RegistryKey key = Registry.CurrentUser.CreateSubKey(keyPath))
                {
                    if (key != null)
                    {
                        key.SetValue("DisplayName", AppName);
                        key.SetValue("DisplayVersion", AppVersion);
                        key.SetValue("Publisher", AppPublisher);
                        key.SetValue("InstallLocation", installDir);
                        key.SetValue("DisplayIcon", File.Exists(iconPath) ? iconPath : targetExe);
                        key.SetValue("UninstallString", "\"" + targetExe + "\" --uninstall");
                        key.SetValue("NoModify", 1, RegistryValueKind.DWord);
                        key.SetValue("NoRepair", 1, RegistryValueKind.DWord);
                    }
                }
            }
            catch { }
        }

        static void Uninstall(string installDir)
        {
            DialogResult res = MessageBox.Show(
                "Are you sure you want to uninstall Raccon Mail Desktop from your computer?",
                "Uninstall Raccon Mail",
                MessageBoxButtons.YesNo,
                MessageBoxIcon.Question
            );

            if (res != DialogResult.Yes) return;

            try
            {
                // Remove shortcuts
                string desktopShortcut = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), AppName + ".lnk");
                if (File.Exists(desktopShortcut)) File.Delete(desktopShortcut);

                string startMenuShortcut = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.Programs), AppName + ".lnk");
                if (File.Exists(startMenuShortcut)) File.Delete(startMenuShortcut);

                // Remove registry entry
                Registry.CurrentUser.DeleteSubKeyTree(@"Software\Microsoft\Windows\CurrentVersion\Uninstall\RacconMail", false);

                MessageBox.Show(
                    "Raccon Mail Desktop has been uninstalled successfully.",
                    "Uninstall Complete",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information
                );
            }
            catch (Exception ex)
            {
                MessageBox.Show("Uninstall error: " + ex.Message, "Error", MessageBoxButtons.OK, MessageBoxIcon.Error);
            }
        }
    }
}
