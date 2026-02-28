# Posts, Comments & Likes — Functionality & How to Present to the Teacher

This document describes **all working functionality** in the Posts section (including comments and likes) and **how to present it** to your teacher.

---

## 1. Functionality That Works

### **Posts**

| Feature | What it does | Where |
|--------|----------------|-------|
| **List posts** | Shows all non-deleted posts, newest first. | `/posts` (Posts page) |
| **View post** | Full post with author, date, image, content, likes count, comments count. | `/posts/{id}` (Read More) |
| **Create post** | Logged-in users can create a post: title, category, content, optional image. | “Create Post” button → modal on `/posts` |
| **Edit post** | Author or admin can edit title, category, content, and replace image. | Post card menu → Edit / Post detail page → Edit |
| **Delete post** | Author or admin can soft-delete (post hidden, not removed from DB). | Post card menu → Delete / Post detail → Delete |
| **Categories** | Post can have category: Health Tips, Nutrition, Mental Health, Fitness. | Create/Edit form |
| **Image upload** | Optional image on create; optional image replace on edit. Stored under `/uploads/posts/`. | Create/Edit form |
| **Filter by category** | Client-side filter: All, Health Tips, Nutrition, Mental Health, Fitness. | Filter buttons on `/posts` |
| **Author & date** | Each post shows author name (and avatar if set), creation date, “(edited)” if updated. | Post card and post detail |
| **Emotional / risk analysis** | On create and edit, content is analyzed (AI if configured, else rules). Post gets emotion, risk level, is_sensitive. | Backend only (used for wellbeing alerts) |
| **High-risk alert** | If analysis marks post as high-risk, admin (and optionally author) gets an email. User sees a flash warning. | Backend + flash message |

### **Comments**

| Feature | What it does | Where |
|--------|----------------|-------|
| **List comments** | All comments for the post under the post content. | Post detail page `/posts/{id}` |
| **Add comment** | Logged-in users can add a comment (text). | Form under “Commentaires” on post detail |
| **Edit comment** | Author or admin can edit comment text via modal. | “Modifier” on each comment (owner/admin) |
| **Delete comment** | Author or admin can delete a comment. | “Supprimer” with confirmation (owner/admin) |
| **Comment author & date** | Each comment shows author name (initial as avatar), date/time. | Comment card |

### **Likes**

| Feature | What it does | Where |
|--------|----------------|-------|
| **Like / unlike** | Logged-in user can like or unlike a post. One like per user per post. | Heart button on post card and on post detail |
| **Like count** | Shows total number of likes. Updates without full page reload (AJAX). | Next to heart on list and detail |
| **Visual state** | Heart is filled when current user liked, outline when not. | Same as above |

### **Security & access**

- **Authentication**  
  Create post, edit/delete own post, like, add/edit/delete own comment require login. Unauthenticated users get redirect + flash or 401 for like API.

- **Authorization**  
  - Edit/delete post: only post author or admin.  
  - Edit/delete comment: only comment author or admin.  
  - Enforced in controllers (e.g. `PostController`, `CommentController`).

- **CSRF**  
  All forms and the like request use CSRF tokens (create_post, edit/delete post, create_comment, edit/delete comment, like).

- **Soft delete**  
  Deleted posts are hidden from list and detail (deletedAt set), not physically removed.

### **Front-end / UX**

- **Modals**  
  Create post, edit post, edit comment use Bootstrap modals (no full-page form for these).

- **Image preview**  
  On create post, chosen image is previewed before submit.

- **Flash messages**  
  Success/error/warning after create, edit, delete (post/comment) and after high-risk warning.

- **Responsive**  
  Posts grid, post detail, comments and modals are responsive.

- **Stimulus/Turbo**  
  Loaded only on `/posts` and `/posts/{id}` for this section (not site-wide).

---

## 2. Main URLs (for demo)

| Action | URL |
|--------|-----|
| Posts list | `/posts` |
| Post detail (with comments & like) | `/posts/{id}` (e.g. `/posts/1`) |
| Create post | POST to `/posts/create` (via form on `/posts`) |
| Edit post | POST to `/posts/{id}/edit` |
| Delete post | POST to `/posts/{id}/delete` |
| Like | POST to `/posts/{id}/like` (AJAX, returns JSON) |
| Add comment | POST to `/comments/create/{postId}` (from post detail) |
| Edit comment | POST to `/comments/{id}/edit` |
| Delete comment | POST to `/comments/{id}/delete` |

---

## 3. How to Present to the Teacher

### **Short intro (30 seconds)**

- “My part of the project is the **community posts** section: **posts**, **comments**, and **likes**.”
- “Users can create, edit, and delete posts; add, edit, and delete comments; and like posts. Everything is secured with login, roles, and CSRF. We also have **emotional/risk analysis** on post content and **alerts** for high-risk posts.”

### **Demo flow (about 5–7 minutes)**

1. **Posts list (`/posts`)**  
   - Show filter buttons (All, Health, Nutrition, Mental, Fitness).  
   - Click “Create Post” → show modal (title, category, content, image).  
   - Optionally create a post and show success message.  
   - Show one post card: author, date, category, content preview, **like count + heart**, comment count, “Read More”.  
   - Show **like**: click heart, count updates without reload.  
   - Show **Edit/Delete** (menu on card): open Edit modal, then optionally Delete with confirmation.

2. **Post detail (`/posts/{id}`)**  
   - “Read More” on a post.  
   - Show full post, same like button (with count), comment count.  
   - Show **comments list**: author, date, text; for owner/admin: Edit (modal) and Delete.  
   - Show **add comment**: type and submit, then show new comment and success message.  
   - Optionally edit a comment in the modal and delete one with confirmation.

3. **Security / wellbeing (talking points)**  
   - “Only the author or admin can edit/delete a post or comment; the server checks this.”  
   - “Every form and the like action use CSRF tokens.”  
   - “Post content is analyzed for emotion and risk; if it’s high-risk, the system can notify the admin (and show a warning to the user).”

### **If the teacher asks “What did you implement?”**

- **Backend:**  
  - `PostController`: list, show, create, edit (soft) delete, like (JSON).  
  - `CommentController`: create, edit, delete.  
  - Use of `EmotionalAnalysisService` and `PostRiskAlertNotifier` on create/edit post.  
  - Entities: `Post`, `Commentaire`, `Like`; relations to `Utilisateur`.  
  - Access control and CSRF on all actions.

- **Front-end:**  
  - Templates: `posts.html.twig` (list + create modal + edit modals), `post_details.html.twig` (post + comments + edit comment modals).  
  - Like button: AJAX POST to `/posts/{id}/like`, update count and heart state.  
  - Category filter (client-side), image preview, Bootstrap modals.

### **One-sentence summary for the teacher**

“In the posts section I implemented full CRUD for posts and comments, like/unlike with live count, category filtering and image upload, plus security (auth, CSRF, author/admin rules) and wellbeing features (emotional analysis and high-risk alerts).”

---

## 4. Optional: What You Could Improve (for discussion)

- **Visibility of Edit/Delete**  
  In the templates, edit/delete are currently shown with `{% if true %}` for testing. For production you can change this to show only to author or admin (e.g. `app.user` and `post.utilisateur.id == app.user.id` or `is_granted('ROLE_ADMIN')`).

- **Pagination**  
  The posts list shows pagination UI but it’s static (no real pagination yet). You could add server-side pagination later.

- **Related posts**  
  Post detail sidebar has a “Related Posts” placeholder; you could fill it by category or author.

---

## 5. Performance (why it was slow and what was fixed)

- **Create post / Edit post slow (several seconds)**  
  When `AI_PROVIDER=openai` in `.env`, every create and edit post calls the OpenAI API. That HTTP request typically takes **2–10+ seconds** and runs **before** the redirect, so the user waits.  
  **Options:**  
  - For faster create/edit: set `AI_PROVIDER=heuristic` in `.env` to use only local keyword analysis (no external API).  
  - Keep OpenAI for production if you need AI analysis; consider moving analysis to a background job (e.g. Symfony Messenger) so the response is immediate.

- **List and detail pages slow (navigation)**  
  This was caused by **N+1 queries**: loading posts, then for each post loading author, likes, and comments in separate queries (dozens of queries for one page).  
  **Fixed:** The posts list and post detail now use repository methods that **eager-load** author, likes, comments, and (on detail) comment authors in one or two queries, so page load and navigation are much faster.

You can use this file as a handout or reference while presenting.
