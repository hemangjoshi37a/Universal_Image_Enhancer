```markdown
# Universal Image Enhancer 🪄✨

Turn any photo into a stunning, AI-enhanced version in **three effortless steps**:

1. **Upload** your image  
2. **Pick** a creativity level (1–5)  
3. **Hit Enhance** – done!

No sign-ups, no watermarks, no steep learning curve – just drag, drop, and let Google’s Gemini model do the magic.

---

## 🚀 Live Demo  
[https://your-github-username.github.io/Universal-Image-Enhancer](https://your-github-username.github.io/Universal-Image-Enhancer)  
*(Replace `your-github-username` with yours after publishing.)*

---

## ⚡️ Features

| Feature | Description |
|---------|-------------|
| **Instant Enhancement** | 3-click workflow: upload → slide → enhance. |
| **5 Creativity Levels** | From subtle touch-ups to fantasy re-imaginations. |
| **Side-by-side Comparison** | Original vs. enhanced displayed instantly. |
| **One-click Download** | Save the new image in original quality. |
| **Persistent History** | Last 30 results cached locally (thumbnails only, tiny storage). |
| **Privacy First** | Images are processed by Google AI **once** and never stored on our servers. |
| **Mobile Friendly** | Responsive UI works on phone, tablet & desktop. |
| **Open Source** | MIT licence – fork, tweak, redistribute freely. |

---

## 🖥️ Tech Stack

- **Front-end**: vanilla HTML5 / CSS3 / ES6 (no frameworks)  
- **Back-end**: single PHP file (`index.php`) – JSON-only endpoint  
- **AI Engine**: Google Gemini (`generateContent` with vision)  
- **Styling**: TailwindCSS (CDN) + custom properties  
- **Icons**: inline SVG (zero dependencies)  
- **Storage**: `localStorage` (client-side) for history & settings

---

## 🛠️ 1-Minute Self-Host

### Requirements
- PHP ≥ 7.4 (built-in server is fine)  
- 50 MB+ free disk space  
- A **Gemini API key** ([get one here](https://aistudio.google.com/app/apikey))

### Quick Start
```bash
# 1. Clone or download this repo
git clone https://github.com/your-github-username/Universal-Image-Enhancer.git
cd Universal-Image-Enhancer

# 2. Start PHP’s dev server
php -S localhost:8000

# 3. Open browser
open http://localhost:8000
```
Paste your API key in the settings modal → enjoy!

---

## 📸 Screenshots

| Upload & Creativity Slider | Result Comparison | History Panel |
|----------------------------|-------------------|---------------|
| ![upload](docs/ss1.png)   | ![compare](docs/ss2.png) | ![history](docs/ss3.png) |

*(Screenshots go in `/docs` folder; add yours.)*

---

## 🧠 How It Works (Simple)

1. Browser resizes large images **client-side** (< 4 MB) for speed.  
2. PHP sends **base64** + prompt to Gemini vision endpoint.  
3. Gemini returns a **new base64 PNG**.  
4. UI shows side-by-side view + download button.  
5. Thumbnail history saved locally (quota-safe).

---

## 🙋‍♂️ Common Issues

| Problem | Fix |
|---------|-----|
| **413 / 500** on big files | Raise `upload_max_filesize` in `php.ini` or let the page auto-shrink. |
| **QuotaExceededError** | Already solved – history uses tiny thumbnails (~30 kB each). |
| **CORS** | Host on same origin or proxy through your PHP server. |
| **Timeout** | Gemini can take 30–90 s for large images – be patient. |

---

## 🧩 Customising

- **Prompts** → edit `$prompts` array in `index.php`.  
- **Max history** → change `30` in `addToHistory()`.  
- **UI colours** → tweak CSS variables in `<style>` block.  
- **New models** → add them in `fetchModels()` when Google releases more.

---

## 🤝 Contributing

Pull requests welcome!  
Please open an issue first for big changes.

1. Fork the repo  
2. Create your feature branch (`git checkout -b feat/amazing-feature`)  
3. Commit your changes (`git commit -m 'Add amazing feature'`)  
4. Push to the branch (`git push origin feat/amazing-feature`)  
5. Open a Pull Request

---

## 📄 Licence

MIT © [Your Name](https://github.com/your-github-username)  
Feel free to use in personal & commercial projects.

---

## 🙏 Credits

- Google Gemini API for the heavy lifting  
- TailwindCSS for rapid styling  
- Community testers & issue reporters ❤️

---

⭐ Star this repo if it saved you time!  
💬 Open an issue for bugs or feature ideas.
```