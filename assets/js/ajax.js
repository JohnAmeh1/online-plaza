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
    notification.className = `ajax-notification fixed top-24 right-6 p-4 rounded-2xl text-white z-50 shadow-2xl backdrop-blur-xl border-2 ${
      type === "success"
        ? "bg-gradient-to-r from-green-400 to-emerald-400 border-green-300"
        : type === "error"
        ? "bg-gradient-to-r from-red-400 to-rose-400 border-red-300"
        : "bg-gradient-to-r from-blue-400 to-cyan-400 border-blue-300"
    }`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 5000);
  }
}

// Post interactions
class PostInteractions {
  static async likePost(likeButton) {
    const postId = likeButton.getAttribute("data-post-id");
    console.log("=== Starting like operation ===");
    console.log("Post ID:", postId);
    console.log("Button element:", likeButton);

    if (!postId) {
      console.error("No post ID found on button");
      throw new Error("Invalid post ID");
    }

    // Find the post card by traversing up from the button
    // We need to skip the button itself and find the parent post card
    // The post card should be a div with class containing 'bg-white' and 'rounded'
    const postCard = likeButton.closest('div.bg-white.rounded-2xl');
    console.log("Post card found:", !!postCard);
    
    if (postCard) {
      console.log("Post card classes:", postCard.className);
      console.log("Post card data-post-id:", postCard.getAttribute('data-post-id'));
    }

    if (!postCard) {
      console.error("Could not find post card container");
      throw new Error("Post card not found");
    }

    // Find elements within the button itself
    const likeIcon = likeButton.querySelector(".like-icon");
    const likeText = likeButton.querySelector(".like-text");
    
    console.log("Like icon found:", !!likeIcon);
    console.log("Like text found:", !!likeText);

    // Find the like count in the stats section (not inside the button)
    // Try multiple strategies to find the like count element
    let likeCountElement = postCard.querySelector(`.like-count[data-post-id="${postId}"]`);
    
    // If not found with data attribute, try without it
    if (!likeCountElement) {
      const allLikeCounts = postCard.querySelectorAll('.like-count');
      console.log("All like-count elements in post card:", allLikeCounts.length);
      
      // Find the one with matching data-post-id
      for (let elem of allLikeCounts) {
        console.log("Checking like-count element:", elem, "data-post-id:", elem.getAttribute('data-post-id'));
        if (elem.getAttribute('data-post-id') == postId) {
          likeCountElement = elem;
          break;
        }
      }
      
      // If still not found, just use the first like-count in this post card
      if (!likeCountElement && allLikeCounts.length > 0) {
        console.log("Using first like-count element as fallback");
        likeCountElement = allLikeCounts[0];
      }
    }
    
    console.log("Like count element found:", !!likeCountElement);
    if (likeCountElement) {
      console.log("Like count element:", likeCountElement);
      console.log("Current like count value:", likeCountElement.textContent);
    }

    if (!likeIcon || !likeText) {
      console.error("Button structure is incorrect");
      console.error("Button HTML:", likeButton.innerHTML);
      throw new Error("Like button structure invalid - missing icon or text");
    }

    if (!likeCountElement) {
      console.error("Like count element not found after all attempts");
      console.error("Post card HTML:", postCard.innerHTML.substring(0, 1000));
      throw new Error("Like count element not found");
    }

    const currentLiked = likeButton.dataset.liked === 'true';
    console.log("Current like state:", currentLiked);

    try {
      const result = await AjaxHelper.request(
        "/online-plaza/posts/api/like.php",
        {
          post_id: postId,
        }
      );

      console.log("Server response:", result);

      if (result.success) {
        // Update UI based on server response
        const isLiked = result.liked;
        console.log("New like state from server:", isLiked);

        if (isLiked) {
          // User now likes the post
          likeIcon.classList.remove("far", "text-gray-600");
          likeIcon.classList.add("fas", "text-green-500");
          likeText.classList.remove("text-gray-600");
          likeText.classList.add("text-green-500");
          likeButton.dataset.liked = "true";
        } else {
          // User no longer likes the post
          likeIcon.classList.remove("fas", "text-green-500");
          likeIcon.classList.add("far", "text-gray-600");
          likeText.classList.remove("text-green-500");
          likeText.classList.add("text-gray-600");
          likeButton.dataset.liked = "false";
        }

        // Update like count
        if (result.like_count !== undefined) {
          likeCountElement.textContent = result.like_count;
          console.log("Like count updated to:", result.like_count);
        }

        // Show success notification
        const message = isLiked ? "Post liked!" : "Post unliked!";
        AjaxHelper.showNotification(message, "success");
        
        console.log("=== Like operation completed successfully ===");
        return result;
      } else {
        throw new Error(result.message || "Like action failed");
      }
    } catch (error) {
      console.error("=== Like operation failed ===");
      console.error("Error:", error);
      AjaxHelper.showNotification(
        error.message || "Failed to update like",
        "error"
      );
      throw error;
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
  notification.className = `custom-notification fixed top-24 right-6 p-4 rounded-2xl text-white z-50 shadow-2xl backdrop-blur-xl border-2 ${
    type === "success"
      ? "bg-gradient-to-r from-green-400 to-emerald-400 border-green-300"
      : "bg-gradient-to-r from-red-400 to-rose-400 border-red-300"
  }`;
  notification.textContent = message;

  document.body.appendChild(notification);

  // Auto remove after 3 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.remove();
    }
  }, 3000);
}

document.addEventListener("DOMContentLoaded", function () {
  console.log("=== DOM Content Loaded - Initializing ===");

  // 1. LIKE BUTTONS - SIMPLIFIED AND FIXED
  console.log("Initializing like buttons...");
  
  const initializeLikeButtons = () => {
    const likeButtons = document.querySelectorAll(".like-btn");
    console.log(`Found ${likeButtons.length} like buttons`);

    likeButtons.forEach((button, index) => {
      const postId = button.getAttribute("data-post-id");
      console.log(`Button ${index + 1}: Post ID = ${postId}`);
      
      // Remove existing listeners by cloning
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);
      
      // Add new listener
      newButton.addEventListener("click", async function (e) {
        e.preventDefault();
        e.stopPropagation();

        console.log(`\n=== Like button clicked for post ${postId} ===`);

        // Prevent double-clicking
        if (this.disabled) {
          console.log("Button disabled, ignoring click");
          return;
        }

        this.disabled = true;

        // Add animation
        const likeIcon = this.querySelector(".like-icon");
        if (likeIcon) {
          likeIcon.classList.add("like-animation");
        }

        try {
          // Pass the button itself to the likePost method
          await PostInteractions.likePost(this);
        } catch (error) {
          console.error("Like operation error:", error);
        } finally {
          // Re-enable button and remove animation
          setTimeout(() => {
            if (likeIcon) {
              likeIcon.classList.remove("like-animation");
            }
            this.disabled = false;
          }, 400);
        }
      });
    });
  };

  // Initialize like buttons
  initializeLikeButtons();

  // 2. Review forms
  console.log("Initializing review forms...");
  const reviewForm = document.querySelector(".review-form");
  if (reviewForm) {
    console.log("Review form found");

    reviewForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const productId = this.getAttribute("data-product-id");
      const ratingInput = this.querySelector('input[name="rating"]:checked');
      const rating = ratingInput ? parseInt(ratingInput.value) : 0;
      const reviewText = this.querySelector(
        'textarea[name="review_text"]'
      ).value.trim();

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
            const postId = formData.get("post_id");
            const commentCountElements = document.querySelectorAll(
              `.comment-btn[data-post-id="${postId}"]`
            );
            commentCountElements.forEach((element) => {
              const countSpan =
                element.parentElement.querySelector(".comment-count");
              if (countSpan && data.comment_count !== undefined) {
                countSpan.textContent = data.comment_count;
              } else if (countSpan) {
                countSpan.textContent = parseInt(countSpan.textContent) + 1;
              }
            });

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

  console.log("=== All event listeners initialized ===");
});