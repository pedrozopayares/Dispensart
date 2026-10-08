import { cn } from "cn"
import { Loader2Icon } from "lucide-react"
import { strings } from "@/lib/strings"

function Spinner({ className, ...props }: React.ComponentProps<"svg">) {
  return (
    <Loader2Icon
      role="status"
      aria-label={strings.a11y.loading}
      className={cn("size-4 animate-spin", className)}
      {...props}
    />
  )
}

export { Spinner }
