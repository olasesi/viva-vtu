"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  getKnowledgeBaseArticle,
  getKnowledgeBaseArticles,
  getKnowledgeBaseCategories,
  type FaqArticleRecord,
  type FaqCategoryRecord,
} from "@/lib/api";
import {
  BookOpen,
  HelpCircle,
  Loader2,
  Search,
} from "lucide-react";
import { toast } from "sonner";

export default function HelpCenterPage() {
  const [categories, setCategories] = useState<FaqCategoryRecord[]>([]);
  const [articles, setArticles] = useState<FaqArticleRecord[]>([]);
  const [activeCategory, setActiveCategory] = useState<string>("");
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState<FaqArticleRecord | null>(null);
  const [articleLoading, setArticleLoading] = useState(false);

  const loadCategories = useCallback(async () => {
    try {
      const res = await getKnowledgeBaseCategories();
      setCategories(res.data || []);
    } catch {
      setCategories([]);
    }
  }, []);

  const loadArticles = useCallback(async () => {
    setLoading(true);
    try {
      const res = await getKnowledgeBaseArticles({
        category: activeCategory || undefined,
        search: search || undefined,
        limit: 100,
      });
      setArticles(res.data || []);
    } catch {
      setArticles([]);
    } finally {
      setLoading(false);
    }
  }, [activeCategory, search]);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  useEffect(() => {
    const handle = setTimeout(() => {
      loadArticles();
    }, 300);

    return () => clearTimeout(handle);
  }, [loadArticles]);

  const grouped = useMemo(() => {
    const groups = new Map<string, { category: FaqCategoryRecord | undefined; items: FaqArticleRecord[] }>();

    for (const article of articles) {
      const key = String(article.faq_category_id);
      const existing = groups.get(key);

      if (existing) {
        existing.items.push(article);
      } else {
        groups.set(key, { category: article.category, items: [article] });
      }
    }

    return Array.from(groups.values());
  }, [articles]);

  const openArticle = async (article: FaqArticleRecord) => {
    setSelected(article);
    setArticleLoading(true);

    try {
      const res = await getKnowledgeBaseArticle(article.slug);
      setSelected(res.data);
    } catch {
      toast.error("Failed to load the article");
    } finally {
      setArticleLoading(false);
    }
  };

  return (
    <div className="space-y-6">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold">
          <BookOpen className="h-6 w-6 text-primary" />
          Help Center
        </h1>
        <p className="text-muted-foreground">
          Find answers to common questions about your wallet, purchases and account
        </p>
      </div>

      <Card>
        <CardContent className="space-y-4 pt-6">
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search the knowledge base"
              className="pl-9"
              aria-label="Search the knowledge base"
            />
          </div>

          <div className="flex flex-wrap gap-2">
            <Button
              size="sm"
              variant={activeCategory === "" ? "default" : "outline"}
              onClick={() => setActiveCategory("")}
            >
              All
            </Button>
            {categories.map((category) => (
              <Button
                key={category.id}
                size="sm"
                variant={activeCategory === category.slug ? "default" : "outline"}
                onClick={() => setActiveCategory(category.slug)}
              >
                {category.name}
                {typeof category.articles_count === "number" && (
                  <span className="ml-2 rounded-full bg-muted px-1.5 text-xs text-muted-foreground">
                    {category.articles_count}
                  </span>
                )}
              </Button>
            ))}
          </div>
        </CardContent>
      </Card>

      {loading ? (
        <div className="flex items-center justify-center py-12 text-muted-foreground">
          <Loader2 className="mr-2 h-5 w-5 animate-spin" />
          Loading articles...
        </div>
      ) : grouped.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center gap-2 py-12 text-center text-muted-foreground">
            <HelpCircle className="h-8 w-8" />
            <p>No articles matched your search.</p>
            <p className="text-sm">
              Try a different keyword or contact support from the Support page.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-6">
          {grouped.map((group, index) => (
            <Card key={group.category?.id ?? index}>
              <CardHeader>
                <CardTitle>{group.category?.name ?? "Articles"}</CardTitle>
                {group.category?.description && (
                  <CardDescription>{group.category.description}</CardDescription>
                )}
              </CardHeader>
              <CardContent className="space-y-2">
                {group.items.map((article) => (
                  <button
                    key={article.id}
                    type="button"
                    onClick={() => openArticle(article)}
                    className="w-full rounded-md border p-4 text-left transition-colors hover:bg-muted/60"
                  >
                    <p className="font-medium">{article.title}</p>
                    {article.excerpt && (
                      <p className="mt-1 text-sm text-muted-foreground">
                        {article.excerpt}
                      </p>
                    )}
                  </button>
                ))}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={Boolean(selected)} onOpenChange={(open) => !open && setSelected(null)}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>{selected?.title}</DialogTitle>
            <DialogDescription>
              {selected?.category?.name}
              {selected?.excerpt ? ` • ${selected.excerpt}` : ""}
            </DialogDescription>
          </DialogHeader>
          {articleLoading ? (
            <div className="flex items-center py-6 text-muted-foreground">
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              Loading...
            </div>
          ) : (
            <div className="max-h-[60vh] overflow-auto whitespace-pre-line text-sm leading-relaxed">
              {selected?.content}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
