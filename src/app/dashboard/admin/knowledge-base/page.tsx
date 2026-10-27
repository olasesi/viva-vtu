"use client";

import { useCallback, useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  createAdminFaqArticle,
  createAdminFaqCategory,
  deleteAdminFaqArticle,
  deleteAdminFaqCategory,
  getAdminFaqArticles,
  getAdminFaqCategories,
  updateAdminFaqArticle,
  updateAdminFaqCategory,
  type FaqArticleRecord,
  type FaqCategoryRecord,
} from "@/lib/api";
import { BookOpen, Pencil, Plus, Trash2 } from "lucide-react";
import { toast } from "sonner";

const emptyCategory = {
  id: 0,
  name: "",
  description: "",
  icon: "",
  sort_order: 0,
  is_active: true,
};

const emptyArticle = {
  id: 0,
  faq_category_id: 0,
  title: "",
  excerpt: "",
  content: "",
  sort_order: 0,
  is_published: true,
};

type CategoryForm = typeof emptyCategory;
type ArticleForm = typeof emptyArticle;

export default function AdminKnowledgeBasePage() {
  const [categories, setCategories] = useState<FaqCategoryRecord[]>([]);
  const [articles, setArticles] = useState<FaqArticleRecord[]>([]);
  const [loadingArticles, setLoadingArticles] = useState(true);
  const [categoryOpen, setCategoryOpen] = useState(false);
  const [articleOpen, setArticleOpen] = useState(false);
  const [categoryForm, setCategoryForm] = useState<CategoryForm>(emptyCategory);
  const [articleForm, setArticleForm] = useState<ArticleForm>(emptyArticle);
  const [saving, setSaving] = useState(false);
  const [filterCategory, setFilterCategory] = useState<string>("");

  const loadCategories = useCallback(async () => {
    try {
      const res = await getAdminFaqCategories();
      setCategories(res.data || []);
    } catch {
      toast.error("Failed to load categories");
    }
  }, []);

  const loadArticles = useCallback(async () => {
    setLoadingArticles(true);
    try {
      const res = await getAdminFaqArticles({
        faq_category_id: filterCategory ? Number(filterCategory) : undefined,
        limit: 100,
      });
      setArticles(res.data?.data || []);
    } catch {
      toast.error("Failed to load articles");
    } finally {
      setLoadingArticles(false);
    }
  }, [filterCategory]);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  useEffect(() => {
    loadArticles();
  }, [loadArticles]);

  const openNewCategory = () => {
    setCategoryForm(emptyCategory);
    setCategoryOpen(true);
  };

  const openEditCategory = (category: FaqCategoryRecord) => {
    setCategoryForm({
      id: category.id,
      name: category.name,
      description: category.description || "",
      icon: category.icon || "",
      sort_order: category.sort_order,
      is_active: category.is_active,
    });
    setCategoryOpen(true);
  };

  const saveCategory = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);

    const payload = {
      name: categoryForm.name,
      description: categoryForm.description || null,
      icon: categoryForm.icon || null,
      sort_order: categoryForm.sort_order,
      is_active: categoryForm.is_active,
    };

    try {
      if (categoryForm.id) {
        await updateAdminFaqCategory(categoryForm.id, payload);
        toast.success("Category updated");
      } else {
        await createAdminFaqCategory(payload);
        toast.success("Category created");
      }

      setCategoryOpen(false);
      loadCategories();
      loadArticles();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || "Failed to save category");
    } finally {
      setSaving(false);
    }
  };

  const removeCategory = async (category: FaqCategoryRecord) => {
    if (!window.confirm(`Delete "${category.name}" and all its articles?`)) {
      return;
    }

    try {
      await deleteAdminFaqCategory(category.id);
      toast.success("Category deleted");
      loadCategories();
      loadArticles();
    } catch {
      toast.error("Failed to delete category");
    }
  };

  const openNewArticle = () => {
    setArticleForm({
      ...emptyArticle,
      faq_category_id: categories[0]?.id ?? 0,
    });
    setArticleOpen(true);
  };

  const openEditArticle = (article: FaqArticleRecord) => {
    setArticleForm({
      id: article.id,
      faq_category_id: article.faq_category_id,
      title: article.title,
      excerpt: article.excerpt || "",
      content: article.content,
      sort_order: article.sort_order,
      is_published: article.is_published,
    });
    setArticleOpen(true);
  };

  const saveArticle = async (event: React.FormEvent) => {
    event.preventDefault();
    setSaving(true);

    const payload = {
      faq_category_id: articleForm.faq_category_id,
      title: articleForm.title,
      excerpt: articleForm.excerpt || null,
      content: articleForm.content,
      sort_order: articleForm.sort_order,
      is_published: articleForm.is_published,
    };

    try {
      if (articleForm.id) {
        await updateAdminFaqArticle(articleForm.id, payload);
        toast.success("Article updated");
      } else {
        await createAdminFaqArticle(payload);
        toast.success("Article created");
      }

      setArticleOpen(false);
      loadArticles();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || "Failed to save article");
    } finally {
      setSaving(false);
    }
  };

  const removeArticle = async (article: FaqArticleRecord) => {
    if (!window.confirm(`Delete "${article.title}"?`)) {
      return;
    }

    try {
      await deleteAdminFaqArticle(article.id);
      toast.success("Article deleted");
      loadArticles();
    } catch {
      toast.error("Failed to delete article");
    }
  };

  const togglePublished = async (article: FaqArticleRecord) => {
    try {
      await updateAdminFaqArticle(article.id, { is_published: !article.is_published });
      loadArticles();
    } catch {
      toast.error("Failed to update article");
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold">
          <BookOpen className="h-6 w-6 text-primary" />
          Knowledge Base
        </h1>
        <p className="text-muted-foreground">
          Manage the help center categories and articles your customers see
        </p>
      </div>

      <Tabs defaultValue="articles">
        <TabsList>
          <TabsTrigger value="articles">Articles</TabsTrigger>
          <TabsTrigger value="categories">Categories</TabsTrigger>
        </TabsList>

        <TabsContent value="articles" className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <select
              className="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={filterCategory}
              onChange={(event) => setFilterCategory(event.target.value)}
              aria-label="Filter by category"
            >
              <option value="">All categories</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>
                  {category.name}
                </option>
              ))}
            </select>
            <Button onClick={openNewArticle} disabled={categories.length === 0}>
              <Plus className="mr-2 h-4 w-4" />
              New Article
            </Button>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Articles</CardTitle>
              <CardDescription>{articles.length} article(s)</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              {articles.map((article) => (
                <div
                  key={article.id}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4"
                >
                  <div>
                    <p className="font-medium">{article.title}</p>
                    <p className="text-sm text-muted-foreground">
                      {article.category?.name ?? "Uncategorised"} • {article.views} view(s)
                    </p>
                    {article.excerpt && (
                      <p className="text-sm text-muted-foreground">{article.excerpt}</p>
                    )}
                  </div>
                  <div className="flex items-center gap-1">
                    <Button
                      size="sm"
                      variant={article.is_published ? "outline" : "secondary"}
                      onClick={() => togglePublished(article)}
                    >
                      {article.is_published ? "Published" : "Draft"}
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      aria-label="Edit article"
                      onClick={() => openEditArticle(article)}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      aria-label="Delete article"
                      onClick={() => removeArticle(article)}
                    >
                      <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
              ))}

              {!loadingArticles && articles.length === 0 && (
                <p className="py-8 text-center text-muted-foreground">
                  No articles yet. Create a category first, then add articles.
                </p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="categories" className="space-y-4">
          <div className="flex justify-end">
            <Button onClick={openNewCategory}>
              <Plus className="mr-2 h-4 w-4" />
              New Category
            </Button>
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Categories</CardTitle>
              <CardDescription>{categories.length} category(ies)</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
              {categories.map((category) => (
                <div
                  key={category.id}
                  className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4"
                >
                  <div>
                    <p className="font-medium flex items-center gap-2">
                      {category.name}
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs ${
                          category.is_active
                            ? "bg-emerald-100 text-emerald-700"
                            : "bg-muted text-muted-foreground"
                        }`}
                      >
                        {category.is_active ? "Active" : "Hidden"}
                      </span>
                    </p>
                    {category.description && (
                      <p className="text-sm text-muted-foreground">{category.description}</p>
                    )}
                    <p className="text-xs text-muted-foreground">
                      {category.articles_count ?? 0} article(s)
                    </p>
                  </div>
                  <div className="flex items-center gap-1">
                    <Button
                      size="icon"
                      variant="ghost"
                      aria-label="Edit category"
                      onClick={() => openEditCategory(category)}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      aria-label="Delete category"
                      onClick={() => removeCategory(category)}
                    >
                      <Trash2 className="h-4 w-4 text-red-500" />
                    </Button>
                  </div>
                </div>
              ))}

              {categories.length === 0 && (
                <p className="py-8 text-center text-muted-foreground">
                  No categories yet. Create one to start adding articles.
                </p>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <Dialog open={categoryOpen} onOpenChange={setCategoryOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {categoryForm.id ? "Edit Category" : "New Category"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={saveCategory} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="category-name">Name</Label>
              <Input
                id="category-name"
                required
                value={categoryForm.name}
                onChange={(event) =>
                  setCategoryForm({ ...categoryForm, name: event.target.value })
                }
                placeholder="Getting Started"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="category-description">Description</Label>
              <Input
                id="category-description"
                value={categoryForm.description}
                onChange={(event) =>
                  setCategoryForm({ ...categoryForm, description: event.target.value })
                }
                placeholder="Basics for new customers"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label htmlFor="category-icon">Icon</Label>
                <Input
                  id="category-icon"
                  value={categoryForm.icon}
                  onChange={(event) =>
                    setCategoryForm({ ...categoryForm, icon: event.target.value })
                  }
                  placeholder="book"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="category-order">Sort Order</Label>
                <Input
                  id="category-order"
                  type="number"
                  min={0}
                  value={categoryForm.sort_order}
                  onChange={(event) =>
                    setCategoryForm({
                      ...categoryForm,
                      sort_order: Number(event.target.value),
                    })
                  }
                />
              </div>
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={categoryForm.is_active}
                onChange={(event) =>
                  setCategoryForm({ ...categoryForm, is_active: event.target.checked })
                }
                className="h-4 w-4 accent-primary"
              />
              Active (visible to customers)
            </label>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setCategoryOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? "Saving..." : "Save Category"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={articleOpen} onOpenChange={setArticleOpen}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {articleForm.id ? "Edit Article" : "New Article"}
            </DialogTitle>
          </DialogHeader>
          <form onSubmit={saveArticle} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="article-category">Category</Label>
              <select
                id="article-category"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={articleForm.faq_category_id}
                onChange={(event) =>
                  setArticleForm({
                    ...articleForm,
                    faq_category_id: Number(event.target.value),
                  })
                }
              >
                {categories.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="article-title">Title</Label>
              <Input
                id="article-title"
                required
                value={articleForm.title}
                onChange={(event) =>
                  setArticleForm({ ...articleForm, title: event.target.value })
                }
                placeholder="How do I fund my wallet?"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="article-excerpt">Excerpt</Label>
              <Input
                id="article-excerpt"
                value={articleForm.excerpt}
                onChange={(event) =>
                  setArticleForm({ ...articleForm, excerpt: event.target.value })
                }
                placeholder="A one-line summary"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="article-content">Content</Label>
              <Textarea
                id="article-content"
                required
                rows={10}
                value={articleForm.content}
                onChange={(event) =>
                  setArticleForm({ ...articleForm, content: event.target.value })
                }
                placeholder="Write the full answer here"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label htmlFor="article-order">Sort Order</Label>
                <Input
                  id="article-order"
                  type="number"
                  min={0}
                  value={articleForm.sort_order}
                  onChange={(event) =>
                    setArticleForm({
                      ...articleForm,
                      sort_order: Number(event.target.value),
                    })
                  }
                />
              </div>
              <label className="flex items-end gap-2 pb-2 text-sm">
                <input
                  type="checkbox"
                  checked={articleForm.is_published}
                  onChange={(event) =>
                    setArticleForm({
                      ...articleForm,
                      is_published: event.target.checked,
                    })
                  }
                  className="h-4 w-4 accent-primary"
                />
                Published
              </label>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setArticleOpen(false)}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? "Saving..." : "Save Article"}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
