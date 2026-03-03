# Contributing to OpenTT

First of all — thank you for taking the time to explore OpenTT.

This project started as a practical solution for managing table tennis competitions at a local club level. It is actively evolving, and contributions, feedback, and ideas are welcome.

---

## 📌 Project Philosophy

OpenTT aims to be:

- Free and open-source
- Transparent and self-hosted
- Practical and community-driven
- Gradually improving in structure and maintainability

The architecture is continuously being refined and modularized.

---

## 🐛 Reporting Issues

If you find a bug:

1. Check if a similar issue already exists.
2. Provide:
   - A clear description
   - Steps to reproduce
   - Expected behavior
   - WordPress + PHP version (if relevant)

Clear reports help fix issues faster.

---

## 💡 Suggesting Features

Feature suggestions are welcome, especially if they:

- Improve real-world league management workflows
- Keep the plugin self-hosted
- Avoid unnecessary complexity
- Align with the project's open-source philosophy

Please describe the actual problem your suggestion solves.

---

## 🌍 Contributing Translations

OpenTT supports a simple file-based localization system for the admin UI.

If you'd like to contribute a translation:

- Translation files are located in:
  `languages/admin-ui-<lang_code>.txt`
- Format:
  `english_reference = translation`
- Use:
  `languages/admin-ui-template.example.txt`
  as a starting template.

New language files are automatically detected and listed in the plugin's Settings page.

Translation contributions are highly appreciated and help make OpenTT accessible to more communities.

---

## 🔀 Pull Requests

Before opening a PR:

- Make sure the code works and does not break existing functionality.
- Keep changes focused and minimal.
- Avoid mixing unrelated refactors in a single PR.
- Write clear commit messages (imperative style preferred).

If your change affects architecture or structure, please explain your reasoning.

---

## 🧱 Code Structure

OpenTT is currently transitioning from a larger core file toward smaller, modular components (helpers, services, etc.).

Goals:

- Separation of concerns
- Clear responsibility per file
- Reduced monolithic logic
- Improved readability and maintainability

Refactor-focused contributions are welcome, but should preserve existing functionality.

---

## 🤝 Communication

Be respectful.
Constructive criticism is encouraged.
This project is both a functional tool and a continuous learning process.

---

## ⚖ License

By contributing, you agree that your contributions will be licensed under the same AGPL license as the project.