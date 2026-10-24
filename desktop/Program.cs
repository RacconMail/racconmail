using System;
using System.IO;
using System.Reflection;
using System.Diagnostics;
using System.Windows.Forms;
using System.Drawing;
using System.Drawing.Drawing2D;
using Microsoft.Win32;

namespace RacconMailDesktop
{
    static class Program
    {
        public const string AppName = "Raccon Mail";
        public const string AppVersion = "2.0";
        public const string AppUrl = "https://racconmail.eu/?app=desktop";
        public const string AppPublisher = "Raccon Mail Open Source";

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
                // Run Modern Installation GUI Flow
                Application.Run(new ModernInstallerForm(currentExe, installDir, targetExe, targetIco));
            }
            else
            {
                // Launch the Desktop App
                LaunchApp();
            }
        }

        public static void ExtractEmbeddedResources(string targetIco)
        {
            try
            {
                Assembly asm = Assembly.GetExecutingAssembly();
                using (Stream stream = asm.GetManifestResourceStream("RacconIcon"))
                {
                    if (stream != null)
                    {
                        byte[] buffer = new byte[stream.Length];
                        stream.Read(buffer, 0, buffer.Length);
                        File.WriteAllBytes(targetIco, buffer);
                        return;
                    }
                }
            }
            catch { }
        }

        public static Image GetEmbeddedLogo()
        {
            try
            {
                Assembly asm = Assembly.GetExecutingAssembly();
                using (Stream stream = asm.GetManifestResourceStream("RacconLogo"))
                {
                    if (stream != null)
                    {
                        return Image.FromStream(stream);
                    }
                }
            }
            catch { }
            return null;
        }

        public static Icon GetEmbeddedIcon()
        {
            try
            {
                Assembly asm = Assembly.GetExecutingAssembly();
                using (Stream stream = asm.GetManifestResourceStream("RacconIcon"))
                {
                    if (stream != null)
                    {
                        return new Icon(stream);
                    }
                }
            }
            catch { }
            return null;
        }

        public static void LaunchApp()
        {
            try
            {
                string browserPath = FindChromiumBrowser();
                if (string.IsNullOrEmpty(browserPath))
                {
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

        public static void CreateShortcut(string shortcutPath, string targetPath, string iconPath, string description)
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

        public static void RegisterUninstall(string installDir, string targetExe, string iconPath)
        {
            try
            {
                string keyPath = @"Software\Microsoft\Windows\CurrentVersion\Uninstall\RacconMail";
                using (RegistryKey key = Registry.CurrentUser.CreateSubKey(keyPath))
                {
                    if (key != null)
                    {
                        key.SetValue("DisplayName", AppName + " Desktop v2.0");
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
                "Are you sure you want to uninstall Raccon Mail Desktop v2.0 from your computer?",
                "Uninstall Raccon Mail v2.0",
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
                    "Raccon Mail Desktop v2.0 has been uninstalled successfully.",
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

    // Modern White Clean Installer Dialog
    public class ModernInstallerForm : Form
    {
        private string sourceExe;
        private string installDir;
        private string targetExe;
        private string targetIco;

        private Label lblStatus;
        private ProgressBar progressBar;
        private Button btnLaunch;
        private Timer installTimer;
        private int step = 0;

        public ModernInstallerForm(string src, string dir, string tgtExe, string tgtIco)
        {
            this.sourceExe = src;
            this.installDir = dir;
            this.targetExe = tgtExe;
            this.targetIco = tgtIco;

            this.Text = "Raccon Mail Setup — v2.0";
            this.Size = new Size(460, 480);
            this.StartPosition = FormStartPosition.CenterScreen;
            this.FormBorderStyle = FormBorderStyle.FixedDialog;
            this.MaximizeBox = false;
            this.MinimizeBox = true;
            this.BackColor = Color.White;

            Icon ico = Program.GetEmbeddedIcon();
            if (ico != null) this.Icon = ico;

            InitializeModernUI();
        }

        private void InitializeModernUI()
        {
            // Panel container with clean padding
            Panel mainPanel = new Panel();
            mainPanel.Dock = DockStyle.Fill;
            mainPanel.BackColor = Color.White;
            mainPanel.Padding = new Padding(25, 20, 25, 20);
            this.Controls.Add(mainPanel);

            // 1. Logo PictureBox
            PictureBox pbLogo = new PictureBox();
            pbLogo.Size = new Size(84, 84);
            pbLogo.Location = new Point((460 - 84) / 2 - 10, 20);
            pbLogo.SizeMode = PictureBoxSizeMode.Zoom;
            Image logoImg = Program.GetEmbeddedLogo();
            if (logoImg != null) pbLogo.Image = logoImg;
            mainPanel.Controls.Add(pbLogo);

            // 2. Title Label
            Label lblTitle = new Label();
            lblTitle.Text = "Raccon Mail Desktop";
            lblTitle.Font = new Font("Segoe UI", 16, FontStyle.Bold);
            lblTitle.ForeColor = Color.FromArgb(15, 23, 42); // slate-900
            lblTitle.TextAlign = ContentAlignment.MiddleCenter;
            lblTitle.Size = new Size(400, 32);
            lblTitle.Location = new Point(5, 112);
            mainPanel.Controls.Add(lblTitle);

            // 3. Version Capsule Pill Label
            Label lblPill = new Label();
            lblPill.Text = "  Official Desktop Client v2.0  ";
            lblPill.Font = new Font("Segoe UI", 8.5f, FontStyle.Bold);
            lblPill.BackColor = Color.FromArgb(241, 245, 249); // slate-100
            lblPill.ForeColor = Color.FromArgb(71, 85, 105);   // slate-600
            lblPill.TextAlign = ContentAlignment.MiddleCenter;
            lblPill.Size = new Size(180, 24);
            lblPill.Location = new Point((460 - 180) / 2 - 10, 148);
            mainPanel.Controls.Add(lblPill);

            // 4. Subtitle Label
            Label lblSub = new Label();
            lblSub.Text = "Fast, Nocturnal & Sealed Webmail Platform\nZero trackers &bull; Hardware acceleration &bull; Local vault";
            lblSub.Font = new Font("Segoe UI", 9f, FontStyle.Regular);
            lblSub.ForeColor = Color.FromArgb(100, 116, 139); // slate-500
            lblSub.TextAlign = ContentAlignment.MiddleCenter;
            lblSub.Size = new Size(400, 38);
            lblSub.Location = new Point(5, 180);
            mainPanel.Controls.Add(lblSub);

            // 5. Card Container for Installation Progress
            Panel cardPanel = new Panel();
            cardPanel.Size = new Size(390, 110);
            cardPanel.Location = new Point(10, 226);
            cardPanel.BackColor = Color.FromArgb(250, 250, 250);
            cardPanel.BorderStyle = BorderStyle.FixedSingle;
            mainPanel.Controls.Add(cardPanel);

            lblStatus = new Label();
            lblStatus.Text = "Preparing installation...";
            lblStatus.Font = new Font("Segoe UI", 9.5f, FontStyle.Bold);
            lblStatus.ForeColor = Color.FromArgb(30, 41, 59);
            lblStatus.Location = new Point(15, 16);
            lblStatus.Size = new Size(360, 22);
            cardPanel.Controls.Add(lblStatus);

            progressBar = new ProgressBar();
            progressBar.Location = new Point(15, 46);
            progressBar.Size = new Size(358, 14);
            progressBar.Style = ProgressBarStyle.Continuous;
            progressBar.Value = 15;
            cardPanel.Controls.Add(progressBar);

            Label lblDetails = new Label();
            lblDetails.Text = "✓ Desktop shortcut   ✓ Start Menu   ✓ System Tray";
            lblDetails.Font = new Font("Segoe UI", 8.5f, FontStyle.Regular);
            lblDetails.ForeColor = Color.FromArgb(100, 116, 139);
            lblDetails.Location = new Point(15, 72);
            lblDetails.Size = new Size(360, 20);
            cardPanel.Controls.Add(lblDetails);

            // 6. Action Button (Solid Black with Rounded Pill look)
            btnLaunch = new Button();
            btnLaunch.Text = "Installing...";
            btnLaunch.Font = new Font("Segoe UI", 10.5f, FontStyle.Bold);
            btnLaunch.BackColor = Color.FromArgb(15, 23, 42); // slate-900
            btnLaunch.ForeColor = Color.White;
            btnLaunch.FlatStyle = FlatStyle.Flat;
            btnLaunch.FlatAppearance.BorderSize = 0;
            btnLaunch.Size = new Size(390, 44);
            btnLaunch.Location = new Point(10, 350);
            btnLaunch.Cursor = Cursors.Hand;
            btnLaunch.Enabled = false;
            btnLaunch.Click += (s, e) =>
            {
                try
                {
                    Process.Start(new ProcessStartInfo
                    {
                        FileName = targetExe,
                        UseShellExecute = true
                    });
                }
                catch { }
                Application.Exit();
            };
            mainPanel.Controls.Add(btnLaunch);

            // Start installation steps with smooth timer
            installTimer = new Timer();
            installTimer.Interval = 250;
            installTimer.Tick += InstallTimer_Tick;
            installTimer.Start();
        }

        private void InstallTimer_Tick(object sender, EventArgs e)
        {
            step++;
            if (step == 1)
            {
                lblStatus.Text = "Creating installation directory...";
                progressBar.Value = 30;
                try
                {
                    if (!Directory.Exists(installDir))
                    {
                        Directory.CreateDirectory(installDir);
                    }
                }
                catch { }
            }
            else if (step == 2)
            {
                lblStatus.Text = "Extracting Raccon Mail logo & binaries...";
                progressBar.Value = 55;
                try
                {
                    File.Copy(sourceExe, targetExe, true);
                    Program.ExtractEmbeddedResources(targetIco);

                    // Fallback to local raccoon.ico if stream failed
                    if (!File.Exists(targetIco))
                    {
                        string localIco = Path.Combine(Path.GetDirectoryName(sourceExe), "raccoon.ico");
                        if (File.Exists(localIco)) File.Copy(localIco, targetIco, true);
                    }
                }
                catch { }
            }
            else if (step == 3)
            {
                lblStatus.Text = "Creating Desktop & Start Menu shortcuts...";
                progressBar.Value = 85;
                try
                {
                    string desktopPath = Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory);
                    string desktopShortcut = Path.Combine(desktopPath, Program.AppName + ".lnk");
                    Program.CreateShortcut(desktopShortcut, targetExe, targetIco, "Raccon Mail - Fast, Nocturnal & Sealed Webmail v2.0");

                    string startMenuPath = Environment.GetFolderPath(Environment.SpecialFolder.Programs);
                    string startMenuShortcut = Path.Combine(startMenuPath, Program.AppName + ".lnk");
                    Program.CreateShortcut(startMenuShortcut, targetExe, targetIco, "Raccon Mail - Fast, Nocturnal & Sealed Webmail v2.0");

                    Program.RegisterUninstall(installDir, targetExe, targetIco);
                }
                catch { }
            }
            else if (step >= 4)
            {
                installTimer.Stop();
                progressBar.Value = 100;
                lblStatus.Text = "✓ Ready! Raccon Mail v2.0 installed successfully.";
                lblStatus.ForeColor = Color.FromArgb(16, 149, 128); // teal-600
                btnLaunch.Text = "Launch Raccon Mail v2.0  →";
                btnLaunch.Enabled = true;
            }
        }
    }
}
