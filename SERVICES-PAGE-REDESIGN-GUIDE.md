# Services Page Modern Redesign Guide

## Overview
I've created a modern, elegant stylesheet for your Services page that matches the beautiful design of the rest of your site. The CSS file is automatically loaded on the `/services` page.

## How to Apply the New Design

### 1. Edit the Services Page in Elementor
- Log in to WordPress admin
- Go to Pages → Find "Services"
- Click "Edit with Elementor"

### 2. Hero Section (What to Expect)
**Create a new section at the top:**
- Add a Container/Section
- Add CSS Class: `services-hero`
- Content structure:
  ```
  H1: Services
  H2: What to Expect
  Paragraphs: (Your service description text)
  Add a box with class `note` for the "NOTE: Participation via Zoom..." text
  ```

### 3. Images Section
**Two-column layout with images:**
- Add Container with CSS Class: `services-content-grid`
- Left column: Main text content
- Right column: The chalice and singing bowl images

### 4. Upcoming Services Section
**Modern card-based layout:**
- Add Container with CSS Class: `upcoming-services`
- H2: "Upcoming Services"
- Paragraph with class `theme`: "Spiritual theme: Cultivating Compassion"
- For each service, create a container with class `service-card`:
  ```
  <div class="service-card">
    <div class="date">Oct 5:</div>
    <div class="title">Strong Like Water</div>
    <div class="speaker">Rev. Kristina Spaude</div>
    <div class="description">Service description text...</div>
  </div>
  ```

### 5. Service Inspirations Section
**Elegant content box:**
- Add Container with CSS Class: `service-inspirations`
- Inside, add another container with class `content-wrapper`
- Add H2, paragraphs, and a button with class `cta-button` for "Where we're located"

### 6. Music & Choir Section
**Modern video grid:**
- Add Container with CSS Class: `music-choir`
- H2: "Music & Choir"
- H3: "Tri-UU Choir Videos"
- Paragraph: Description text
- Create Container with CSS Class: `video-grid`
- Inside, add each video as:
  ```
  <div class="video-item">
    <a href="[video-url]">
      <img src="[thumbnail]">
      <div class="video-title">Song Title</div>
    </a>
  </div>
  ```
- Add Container with CSS Class `choir-photos` for the 3 choir photos

### 7. Hymn Favorites Link
**Eye-catching call-to-action:**
- Add Container with CSS Class: `hymn-link`
- H2: "And to hear any of nearly 100 Tri-UU hymn favorites..."
- Button link with text "Click Here"

### 8. Past Services Section
**Social media links:**
- Add Container with CSS Class: `past-services`
- H2: "Past Services"
- Container with class `social-links`
- Add links with class `social-link` for Facebook and YouTube

## Design Features

### Color Scheme
- Primary Purple: #5A2B80
- Accent Purple: #7B3FA8
- Background: #FFFFF9 (cream) and white
- Text: Various grays for hierarchy

### Typography
- Headlines: Manrope (bold, modern)
- Body text: Manrope (clean, readable)
- Accents: Barlow Condensed (condensed, elegant)

### Interactive Elements
- Cards lift on hover
- Buttons scale and glow
- Smooth transitions throughout
- Video thumbnails pop up on hover

### Mobile Responsive
- Automatically adjusts to mobile screens
- Single column layout on phones
- Touch-friendly spacing
- Optimized readability

## Quick Start Option
If you prefer, I can help you create the page content directly in WordPress. Just let me know and I'll:
1. Create the HTML structure
2. Import it into your Services page
3. Configure all the Elementor settings

## Preview
The design includes:
✓ Purple gradient hero section
✓ Clean card-based service listings
✓ Modern video grid layout
✓ Elegant typography hierarchy
✓ Smooth hover animations
✓ Fully mobile responsive
✓ Matches your site's color scheme perfectly

Let me know if you'd like me to implement this for you directly!
