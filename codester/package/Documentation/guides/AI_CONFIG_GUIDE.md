# AppointPro – AI Booking Assistant Guide

## Overview
AppointPro includes an optional intelligent conversational assistant designed to answer customer service inquiries and recommend appropriate services in natural language.

> **CRITICAL**: The AI assistant is completely optional, disabled by default, and requires the site administrator to provide their own API key. AppointPro strictly feeds verified database services and real-time slots into the model, ensuring the AI never hallucinates available times or inaccurate prices.

## Supported Providers
- **OpenAI** (GPT-4o, GPT-4o-mini)
- **Google Gemini** (Gemini 1.5 Flash / Pro)
- **Anthropic Claude** (Claude 3.5 Sonnet)

## Configuration Steps
1. Navigate to **AppointPro** -> **AI Assistant**.
2. Toggle **Enable AI Assistant**.
3. Select your preferred provider (e.g., OpenAI).
4. Enter your private API Key.
5. Select the model (default: `gpt-4o-mini` for fast, cost-effective responses).
6. Click **Save Settings**.
7. Test the interactive chat box directly within the admin dashboard before enabling frontend display.
