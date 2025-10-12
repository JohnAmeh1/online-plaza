// AJAX utility functions
class AjaxHelper {
  static async request(url, data = null, method = "POST") {
    const options = {
      method: method,
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      credentials: "same-origin",
    };

    console.log("Making request to:", url, "with data:", data);

    if (data && method !== "GET") {
      const formData = new URLSearchParams();
      for (const key in data) {
        if (data.hasOwnProperty(key)) {
          formData.append(key, data[key]);
        }
      }
      options.body = formData;
    } else if (data && method === "GET") {
      const params = new URLSearchParams(data);
      url += "?" + params.toString();
    }

    try {
      const response = await fetch(url, options);

      // Check if response is JSON
      const contentType = response.headers.get("content-type");
      if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        console.error("Non-JSON response received:", text.substring(0, 200));
        throw new Error(
          `Server returned HTML instead of JSON. Status: ${response.status}`
        );
      }

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      console.log("Response received:", result);
      return result;
    } catch (error) {
      console.error("AJAX request failed:", error);
      console.error("URL:", url);
      console.error("Data:", data);
      return {
        success: false,
        message: "Network error occurred: " + error.message,
      };
    }
  }

  static showNotification(message, type = "success") {
    // Remove existing notifications
    const existingNotifications =
      document.querySelectorAll(".ajax-notification");
    existingNotifications.forEach((notification) => notification.remove());

    // Create new notification
    const notification = document.createElement("div");
    notification.className = `ajax-notification fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
      type === "success"
        ? "bg-green-500 text-white"
        : type === "error"
        ? "bg-red-500 text-white"
        : "bg-blue-500 text-white"
    }`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
      notification.remove();
    }, 5000);
  }
}

// Post interactions
class PostInteractions {
  static async likePost(postId) {
    console.log("Liking post:", postId);

    const likeButton = document.querySelector(
      `.like-btn[data-post-id="${postId}"]`
    );
    const likeCount = likeButton?.parentElement.querySelector(".like-count");
    const likeIcon = likeButton?.querySelector(".like-icon");

    if (!likeButton || !likeCount || !likeIcon) {
      console.error("Like button elements not found");
      return;
    }

    // Get current state
    const isCurrentlyLiked = likeButton.dataset.liked === "true";

    console.log("Current like state:", isCurrentlyLiked);

    const result = await AjaxHelper.request(
      "/online-plaza/posts/api/like.php",
      {
        post_id: postId,
      }
    );

    console.log("Like result:", result);

    if (result.success) {
      // Update UI based on server response
      if (result.liked) {
        likeIcon.classList.replace("far", "fas");
        likeIcon.classList.add("text-red-500");
        likeButton.dataset.liked = "true";
      } else {
        likeIcon.classList.replace("fas", "far");
        likeIcon.classList.remove("text-red-500");
        likeButton.dataset.liked = "false";
      }

      // Update like count
      if (result.like_count !== undefined) {
        likeCount.textContent = result.like_count;
      }

      // Show appropriate message
      const message = result.liked ? "Post liked!" : "Post unliked!";
      AjaxHelper.showNotification(message, "success");
      return result;
    } else {
      AjaxHelper.showNotification(
        result.message || "Failed to update like",
        "error"
      );
      throw new Error(result.message || "Like action failed");
    }
  }
}

// Product interactions
class ProductInteractions {
  static async addReview(productId, rating, reviewText) {
    console.log("Adding review for product:", productId, "Rating:", rating);

    const result = await AjaxHelper.request(
      "/online-plaza/products/api/review.php",
      {
        product_id: productId,
        rating: rating,
        review_text: reviewText,
      }
    );

    console.log("Review submission result:", result);

    if (result.success) {
      // Add review to the list
      const reviewsList = document.querySelector(".reviews-list");
      if (reviewsList) {
        const reviewElement = document.createElement("div");
        reviewElement.className = "review border-b border-gray-200 pb-6";
        reviewElement.innerHTML = `
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                ${result.review.username
                                  .charAt(0)
                                  .toUpperCase()}
                            </div>
                            <div>
                                <h4 class="font-semibold">${
                                  result.review.first_name
                                } ${result.review.last_name}</h4>
                                <p class="text-gray-500 text-sm">@${
                                  result.review.username
                                }</p>
                            </div>
                        </div>
                        <div class="flex items-center text-yellow-400">
                            ${"★".repeat(result.review.rating)}${"☆".repeat(
          5 - result.review.rating
        )}
                        </div>
                    </div>
                    <p class="text-gray-700 mb-2">${reviewText}</p>
                    <p class="text-gray-500 text-sm">${
                      result.review.created_at
                    }</p>
                `;

        // If no reviews exist, replace the empty state
        const emptyState = reviewsList.querySelector(".text-center");
        if (emptyState) {
          reviewsList.innerHTML = "";
        }

        reviewsList.prepend(reviewElement);
      }

      // Update average rating
      this.updateRatingDisplay(result.average_rating, result.review_count);

      // Hide review form and show success message
      this.hideReviewForm();

      AjaxHelper.showNotification(
        result.message || "Review added successfully"
      );

      return true;
    } else {
      AjaxHelper.showNotification(
        result.message || "Failed to submit review",
        "error"
      );
      return false;
    }
  }

  static updateRatingDisplay(averageRating, reviewCount) {
    // Update average rating text
    const avgRatingElement = document.querySelector(".average-rating");
    if (avgRatingElement && averageRating) {
      avgRatingElement.textContent = averageRating;
    }

    // Update review count
    const reviewCountElement = document.querySelector(".review-count");
    if (reviewCountElement && reviewCount) {
      reviewCountElement.textContent = reviewCount;
    }

    // Update star display in product header
    const stars = document.querySelectorAll(".fa-star");
    if (stars.length > 0 && averageRating) {
      const roundedRating = Math.round(averageRating);
      stars.forEach((star, index) => {
        if (index < roundedRating) {
          star.classList.add("text-yellow-400");
          star.classList.remove("text-gray-300");
        } else {
          star.classList.remove("text-yellow-400");
          star.classList.add("text-gray-300");
        }
      });
    }
  }

  static hideReviewForm() {
    const reviewForm = document.querySelector(".review-form");
    if (reviewForm) {
      reviewForm.style.display = "none";

      const successMessage = document.createElement("div");
      successMessage.className = "bg-blue-50 p-6 rounded-lg mb-8 text-center";
      successMessage.innerHTML = `
                <i class="fas fa-check-circle text-blue-500 text-2xl mb-2"></i>
                <p class="text-blue-700 font-semibold">Thank you for your review!</p>
                <p class="text-blue-600 text-sm mt-1">Your review has been submitted successfully.</p>
            `;
      reviewForm.parentNode.insertBefore(successMessage, reviewForm);
    }
  }
}

// Notification function for comments
function showNotification(message, type) {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(
    ".custom-notification"
  );
  existingNotifications.forEach((notification) => notification.remove());

  const notification = document.createElement("div");
  notification.className = `custom-notification fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
    type === "success" ? "bg-green-500" : "bg-red-500"
  }`;
  notification.textContent = message;

  document.body.appendChild(notification);

  // Auto remove after 3 seconds
  setTimeout(() => {
    notification.remove();
  }, 3000);
}

// Initialize all event listeners when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM loaded, initializing all event listeners...");

  // 1. Like buttons
  console.log("Initializing like buttons...");
  document.querySelectorAll(".like-btn").forEach((button) => {
    button.addEventListener("click", async function () {
      const postId = this.getAttribute("data-post-id");
      const likeIcon = this.querySelector(".like-icon");
      const likeCount = this.parentElement.querySelector(".like-count");

      console.log("Like button clicked for post:", postId);
      console.log("Current like state:", this.dataset.liked);

      // Add visual feedback
      likeIcon.classList.add("like-animation");

      try {
        await PostInteractions.likePost(postId);
      } catch (error) {
        console.error("Like error:", error);
      } finally {
        // Remove animation after a short delay
        setTimeout(() => {
          likeIcon.classList.remove("like-animation");
        }, 400);
      }
    });
  });

  // 2. Review forms
  console.log("Initializing review forms...");
  const reviewForm = document.querySelector(".review-form");
  if (reviewForm) {
    console.log("Review form found, attaching event listener...");

    reviewForm.addEventListener("submit", function (e) {
      e.preventDefault();
      console.log("Review form submitted");

      const productId = this.getAttribute("data-product-id");
      const ratingInput = this.querySelector('input[name="rating"]:checked');
      const rating = ratingInput ? parseInt(ratingInput.value) : 0;
      const reviewText = this.querySelector(
        'textarea[name="review_text"]'
      ).value.trim();

      console.log(
        "Form data - Product ID:",
        productId,
        "Rating:",
        rating,
        "Review text length:",
        reviewText.length
      );

      if (!rating) {
        AjaxHelper.showNotification("Please select a rating", "error");
        return;
      }

      if (!reviewText) {
        AjaxHelper.showNotification("Please write a review", "error");
        return;
      }

      if (!productId) {
        AjaxHelper.showNotification("Invalid product", "error");
        return;
      }

      // Show loading state
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin mr-2"></i> Submitting...';

      console.log("Calling ProductInteractions.addReview...");

      ProductInteractions.addReview(productId, rating, reviewText).finally(
        () => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      );
    });
  } else {
    console.log("No review form found on this page");
  }

  // 3. Star rating interaction
  const ratingStars = document.querySelectorAll(".rating-star");
  if (ratingStars.length > 0) {
    let selectedRating = 0;

    ratingStars.forEach((star) => {
      star.addEventListener("click", function () {
        const rating = parseInt(this.getAttribute("data-rating"));
        selectedRating = rating;

        // Update stars display
        ratingStars.forEach((s) => {
          const starRating = parseInt(s.getAttribute("data-rating"));
          if (starRating <= rating) {
            s.classList.add("text-yellow-400");
            s.classList.remove("text-gray-300", "text-yellow-300");
          } else {
            s.classList.remove("text-yellow-400", "text-yellow-300");
            s.classList.add("text-gray-300");
          }
        });

        // Update hidden radio button
        const radioInput = document.querySelector(
          `input[name="rating"][value="${rating}"]`
        );
        if (radioInput) {
          radioInput.checked = true;
        }
      });

      star.addEventListener("mouseenter", function () {
        if (!selectedRating) {
          const rating = parseInt(this.getAttribute("data-rating"));
          ratingStars.forEach((s) => {
            const starRating = parseInt(s.getAttribute("data-rating"));
            if (starRating <= rating) {
              s.classList.add("text-yellow-300");
              s.classList.remove("text-gray-300");
            }
          });
        }
      });

      star.addEventListener("mouseleave", function () {
        if (!selectedRating) {
          ratingStars.forEach((s) => {
            s.classList.remove("text-yellow-300");
            s.classList.add("text-gray-300");
          });
        }
      });
    });
  }

  // 4. Comment forms
  const commentForm = document.getElementById("commentForm");
  if (commentForm) {
    commentForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      const submitButton = this.querySelector('button[type="submit"]');
      const originalText = submitButton.innerHTML;

      // Show loading state
      submitButton.disabled = true;
      submitButton.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Posting...';

      fetch("/online-plaza/posts/api/comment.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Add new comment to the list
            const commentsList = document.getElementById("commentsList");
            const noCommentsMsg = commentsList.querySelector("p.text-center");

            // Remove "no comments" message if it exists
            if (noCommentsMsg) {
              noCommentsMsg.remove();
            }

            // Create new comment element
            const newComment = document.createElement("div");
            newComment.className = "comment bg-gray-50 p-4 rounded-lg";
            newComment.innerHTML = `
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold mr-2">
                                        ${data.comment.username
                                          .charAt(0)
                                          .toUpperCase()}
                                    </div>
                                    <span class="font-semibold">${
                                      data.comment.username
                                    }</span>
                                </div>
                                <span class="text-gray-500 text-sm">${
                                  data.comment.created_at
                                }</span>
                            </div>
                            <p class="text-gray-700">${data.comment.comment}</p>
                        `;

            // Add new comment to top of list
            commentsList.insertBefore(newComment, commentsList.firstChild);

            // Update comment count
            const commentCount = document.querySelector(".comment-count");
            if (commentCount) {
              commentCount.textContent = parseInt(commentCount.textContent) + 1;
            }

            // Reset form
            this.reset();

            // Show success message
            showNotification("Comment posted successfully!", "success");
          } else {
            showNotification(data.message, "error");
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showNotification("An error occurred while posting comment", "error");
        })
        .finally(() => {
          // Reset button state
          submitButton.disabled = false;
          submitButton.innerHTML = originalText;
        });
    });
  }

  console.log("All event listeners initialized successfully");
});
